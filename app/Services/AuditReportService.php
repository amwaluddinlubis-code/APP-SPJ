<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Builds read-only audit reports from the active year and fund source context. */
class AuditReportService
{
    /**
     * @return array{
     *     year:FiscalYear,
     *     fundSource:?FundSource,
     *     summary:array<string,int|float>,
     *     reconciliationRows:Collection<int,object>,
     *     register:Collection<int,Transaction>,
     *     taxSummary:Collection<int,object>,
     *     taxRows:Collection<int,Transaction>,
     *     completenessRows:Collection<int,object>,
     *     syncRuns:Collection<int,object>,
     *     auditLogs:Collection<int,object>,
     *     limitations:array<int,string>
     * }
     */
    public function build(): array
    {
        $yearId = (int) session('active_fiscal_year_id');
        $fundSourceId = (int) session('active_fund_source_id');
        $year = FiscalYear::query()->findOrFail($yearId);
        $fundSource = FundSource::query()->find($fundSourceId);
        $db = DB::connection('school');

        $rkas = $db->table('arkas_rkas_items')
            ->where('fiscal_year_id', $yearId)
            ->where('fund_source_id', $fundSourceId)
            ->get();
        $bku = $db->table('arkas_bku_rows')
            ->where('fiscal_year_id', $yearId)
            ->where('fund_source_id', $fundSourceId)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
        $transactions = Transaction::query()
            ->where('fiscal_year_id', $yearId)
            ->where('fund_source_id', $fundSourceId)
            ->with(['items', 'spjPackage'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (Transaction $transaction): string => $transaction->no_bukti ?: 'ID-'.$transaction->id)
            ->values();

        $rkasById = $rkas->keyBy('source_rapbs_id');
        $bkuBelanja = $bku->filter(fn (object $row): bool => strtoupper((string) $row->category) === 'BELANJA');
        $bkuByNumber = $bkuBelanja->filter(fn (object $row): bool => filled($row->no_bukti))->groupBy('no_bukti');
        $transactionByNumber = $transactions->filter(fn (Transaction $transaction): bool => filled($transaction->no_bukti))->keyBy('no_bukti');
        $numbers = $bkuByNumber->keys()->merge($transactionByNumber->keys())->unique()->sort()->values();

        $reconciliationRows = $numbers->map(function (string $number) use ($bkuByNumber, $transactionByNumber, $rkasById): object {
            $bkuRows = $bkuByNumber->get($number, collect());
            $transaction = $transactionByNumber->get($number);
            $bkuAmount = (float) $bkuRows->sum('amount');
            $transactionAmount = (float) ($transaction?->gross_amount ?? 0);
            $rkasIds = $bkuRows->flatMap(function (object $row): Collection {
                $payload = is_string($row->payload ?? null) ? json_decode($row->payload, true) : [];

                return collect([(string) ($payload['ID_RAPBS'] ?? '')])->filter();
            })->unique()->values();
            $rkasAmount = (float) $rkasIds->sum(fn (string $id): float => (float) ($rkasById->get($id)?->amount ?? 0));
            $variance = $transactionAmount - $bkuAmount;
            $status = ! $transaction
                ? 'BKU TANPA TRANSAKSI'
                : ($bkuRows->isEmpty()
                    ? 'TRANSAKSI TANPA BKU'
                    : (abs($variance) > 0.01 ? 'SELISIH' : 'SESUAI'));

            $row = (object) [
                'no_bukti' => $number,
                'transaction_date' => $transaction?->transaction_date ?? ($bkuRows->first()?->transaction_date ? Carbon::parse($bkuRows->first()->transaction_date) : null),
                'rkas_ids' => $rkasIds->implode(', '),
                'rkas_amount' => $rkasAmount,
                'bku_amount' => $bkuAmount,
                'transaction_amount' => $transactionAmount,
                'variance' => $variance,
                'spj_status' => $transaction?->spjPackage?->document_number ? 'BERNOMOR' : ($transaction?->spjPackage ? 'DRAFT' : 'BELUM ADA'),
                'status' => $status,
            ];

            return $row;
        });

        $taxRows = $transactions->filter(fn (Transaction $transaction): bool => (float) $transaction->tax_total > 0)->values();
        $taxSummary = collect([
            ['label' => 'PPN', 'field' => 'ppn'],
            ['label' => 'PPh 21', 'field' => 'pph21'],
            ['label' => 'PPh 22', 'field' => 'pph22'],
            ['label' => 'PPh 23', 'field' => 'pph23'],
            ['label' => 'PPh 4(2)', 'field' => 'pph4'],
            ['label' => 'SSPD', 'field' => 'sspd'],
        ])->map(function (array $tax) use ($taxRows): object {
            return (object) [
                'label' => $tax['label'],
                'count' => $taxRows->filter(fn (Transaction $transaction): bool => (float) $transaction->{$tax['field']} > 0)->count(),
                'amount' => (float) $taxRows->sum($tax['field']),
            ];
        });

        $completenessRows = $transactions->map(function (Transaction $transaction): object {
            $issues = [];
            if ($transaction->items->isEmpty()) {
                $issues[] = 'Rincian transaksi belum tersedia';
            }
            if (! $transaction->spjPackage) {
                $issues[] = 'Paket SPJ belum disiapkan';
            } elseif (! $transaction->spjPackage->document_number) {
                $issues[] = 'Nomor SPJ belum ditetapkan';
            }
            foreach ([
                'recipient_name' => 'Penerima belum diisi',
                'description' => 'Uraian belum diisi',
                'activity_code' => 'Kegiatan belum diisi',
                'account_code' => 'Rekening belum diisi',
            ] as $field => $message) {
                if (blank($transaction->{$field})) {
                    $issues[] = $message;
                }
            }

            return (object) [
                'id' => $transaction->id,
                'no_bukti' => $transaction->no_bukti,
                'transaction_date' => $transaction->transaction_date,
                'recipient_name' => $transaction->recipient_name,
                'amount' => (float) $transaction->gross_amount,
                'issue_count' => count($issues),
                'issues' => $issues,
                'status' => $issues === [] ? 'LENGKAP' : 'PERLU TINDAKAN',
            ];
        })->values();

        $syncRuns = $db->table('sync_runs')
            ->where('fiscal_year_id', $yearId)
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('fiscal_years')
                ->whereColumn('fiscal_years.id', 'sync_runs.fiscal_year_id')
                ->where('fiscal_years.fund_source_id', $fundSourceId))
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();
        $auditLogs = Schema::connection('school')->hasTable('operational_audit_logs')
            ? $db->table('operational_audit_logs')
                ->where('fiscal_year_id', $yearId)
                ->whereExists(fn ($query) => $query->selectRaw('1')
                    ->from('fiscal_years')
                    ->whereColumn('fiscal_years.id', 'operational_audit_logs.fiscal_year_id')
                    ->where('fiscal_years.fund_source_id', $fundSourceId))
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
            : collect();

        $spjPackaged = $transactions->filter(fn (Transaction $transaction): bool => (bool) $transaction->spjPackage)->count();
        $spjNumbered = $transactions->filter(fn (Transaction $transaction): bool => (bool) $transaction->spjPackage?->document_number)->count();
        $mismatchCount = $reconciliationRows->reject(fn (object $row): bool => $row->status === 'SESUAI')->count();

        return [
            'year' => $year,
            'fundSource' => $fundSource,
            'summary' => [
                'budget' => (float) $rkas->sum('amount'),
                'bku' => (float) $bkuBelanja->sum('amount'),
                'transactions' => (float) $transactions->sum('gross_amount'),
                'tax' => (float) $taxRows->sum('tax_total'),
                'transactionCount' => $transactions->count(),
                'bkuCount' => $bkuBelanja->count(),
                'spjPackaged' => $spjPackaged,
                'spjNumbered' => $spjNumbered,
                'mismatchCount' => $mismatchCount,
                'exceptionCount' => $completenessRows->where('status', 'PERLU TINDAKAN')->count(),
                'syncCount' => $syncRuns->count(),
                'auditCount' => $auditLogs->count(),
            ],
            'reconciliationRows' => $reconciliationRows,
            'register' => $transactions,
            'taxSummary' => $taxSummary,
            'taxRows' => $taxRows,
            'completenessRows' => $completenessRows,
            'syncRuns' => $syncRuns,
            'auditLogs' => $auditLogs,
            'limitations' => [
                'Laporan hanya membaca data yang sudah tersinkron dari ARKAS pada tahun dan sumber dana aktif.',
                'Nominal BKU diringkas per nomor bukti; baris pajak tidak dihitung sebagai transaksi belanja kedua.',
                'Nilai RKAS pada baris rekonsiliasi hanya terhubung jika BKU menyediakan ID_RAPBS; total RKAS tetap ditampilkan pada ringkasan.',
                'Kelengkapan SPJ memeriksa rincian transaksi, paket, nomor SPJ, dan metadata utama; tidak menggantikan pemeriksaan dokumen fisik.',
                Schema::connection('school')->hasTable('operational_audit_logs')
                    ? 'Riwayat operasional berasal dari log aplikasi yang tersedia.'
                    : 'Tabel riwayat operasional belum tersedia pada database sekolah aktif.',
            ],
        ];
    }
}
