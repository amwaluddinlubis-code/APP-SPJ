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
    public function __construct(private SpjProcurementPolicyService $procurementPolicy) {}

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
     *     inspectionIndex:Collection<int,object>,
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
            ->with([
                'items',
                'goods',
                'workOrder',
                'workers',
                'travels',
                'honors',
                'payments',
                'goodsReceipts',
                'spjPackage.documents',
            ])
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

            return (object) [
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
            $policy = $this->procurementPolicy->forTransaction($transaction);

            if ($transaction->items->isEmpty()) {
                $issues[] = 'Rincian transaksi belum tersedia';
            }
            if (! $transaction->spjPackage) {
                $issues[] = 'Paket SPJ belum disiapkan';
            } elseif (! $transaction->spjPackage->document_number) {
                $issues[] = 'Nomor SPJ belum ditetapkan';
            }
            if (blank($transaction->effective_receipt_recipient_name)) {
                $issues[] = 'Penerima kuitansi/A2 belum diisi';
            }
            if (blank($transaction->payment_description ?: $transaction->description)) {
                $issues[] = 'Uraian pembayaran untuk kuitansi/A2 belum diisi';
            }
            foreach ([
                'activity_code' => 'Kegiatan belum diisi',
                'account_code' => 'Rekening belum diisi',
            ] as $field => $message) {
                if (blank($transaction->{$field})) {
                    $issues[] = $message;
                }
            }
            if ($policy['channel'] === 'SIPLAH') {
                if (blank($transaction->payment_reference) && blank($transaction->invoice_number)) {
                    $issues[] = 'Referensi pesanan/invoice SIPLah belum tersedia';
                }
                if (blank($transaction->vendor_name)) {
                    $issues[] = 'Penyedia SIPLah belum tersedia';
                }
            }

            return (object) [
                'id' => $transaction->id,
                'no_bukti' => $transaction->no_bukti,
                'transaction_date' => $transaction->transaction_date,
                'recipient_name' => $transaction->effective_receipt_recipient_name,
                'amount' => (float) $transaction->gross_amount,
                'channel' => $policy['channel_label'],
                'issue_count' => count($issues),
                'issues' => $issues,
                'status' => $issues === [] ? 'LENGKAP' : 'PERLU TINDAKAN',
            ];
        })->values();

        $reconciliationByNumber = $reconciliationRows->keyBy('no_bukti');
        $inspectionIndex = $transactions->values()->map(function (Transaction $transaction, int $index) use ($reconciliationByNumber): object {
            $policy = $this->procurementPolicy->forTransaction($transaction);
            $documents = $this->inspectionDocuments($transaction, $reconciliationByNumber->get((string) $transaction->no_bukti));
            $applicable = collect($documents)->where('status', '!=', 'TIDAK BERLAKU');
            $ready = $applicable->where('status', 'TERSEDIA')->count();

            return (object) [
                'index' => $index + 1,
                'transaction_id' => $transaction->id,
                'no_bukti' => $transaction->no_bukti,
                'transaction_date' => $transaction->transaction_date,
                'description' => $transaction->payment_description ?: $transaction->description,
                'recipient_name' => $transaction->effective_receipt_recipient_name,
                'category' => strtoupper((string) ($transaction->spj_category ?: 'LAINNYA')),
                'procurement_channel' => $policy['channel'],
                'procurement_channel_label' => $policy['channel_label'],
                'amount' => (float) $transaction->gross_amount,
                'package_number' => $transaction->spjPackage?->document_number,
                'package_status' => $transaction->spjPackage?->status ?: 'BELUM ADA',
                'documents' => $documents,
                'ready_count' => $ready,
                'applicable_count' => $applicable->count(),
                'is_ready' => $applicable->isNotEmpty() && $ready === $applicable->count(),
            ];
        });

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
        $siplahCount = $transactions->filter(fn (Transaction $transaction): bool => $this->procurementPolicy->isSiplah($transaction))->count();

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
                'siplahCount' => $siplahCount,
                'nonSiplahCount' => $transactions->count() - $siplahCount,
                'mismatchCount' => $mismatchCount,
                'exceptionCount' => $completenessRows->where('status', 'PERLU TINDAKAN')->count(),
                'inspectionReadyCount' => $inspectionIndex->where('is_ready', true)->count(),
                'syncCount' => $syncRuns->count(),
                'auditCount' => $auditLogs->count(),
            ],
            'reconciliationRows' => $reconciliationRows,
            'register' => $transactions,
            'taxSummary' => $taxSummary,
            'taxRows' => $taxRows,
            'completenessRows' => $completenessRows,
            'inspectionIndex' => $inspectionIndex,
            'syncRuns' => $syncRuns,
            'auditLogs' => $auditLogs,
            'limitations' => [
                'Laporan hanya membaca data yang sudah tersinkron dari ARKAS pada tahun dan sumber dana aktif.',
                'Nominal BKU diringkas per nomor bukti; baris pajak tidak dihitung sebagai transaksi belanja kedua.',
                'Nilai RKAS pada baris rekonsiliasi hanya terhubung jika BKU menyediakan ID_RAPBS; total RKAS tetap ditampilkan pada ringkasan.',
                'Kelengkapan SPJ memeriksa rincian transaksi, paket, nomor SPJ, dan metadata utama; tidak menggantikan pemeriksaan dokumen fisik.',
                'Transaksi SIPLah mempertahankan bukti pengadaan dari platform, tetapi Kuitansi/Bukti Kas Pengeluaran (A2) tetap wajib dibuat dan dicetak dari aplikasi.',
                'Daftar isi pemeriksaan per transaksi adalah alat bantu kesiapan dokumen. Status bukti pajak tidak menyatakan bahwa berkas bukti setor fisik/digital sudah terunggah.',
                Schema::connection('school')->hasTable('operational_audit_logs')
                    ? 'Riwayat operasional berasal dari log aplikasi yang tersedia.'
                    : 'Tabel riwayat operasional belum tersedia pada database sekolah aktif.',
            ],
        ];
    }

    /** @return array<int,object> */
    private function inspectionDocuments(Transaction $transaction, ?object $reconciliation): array
    {
        $documents = [];
        $policy = $this->procurementPolicy->forTransaction($transaction);
        $isSiplah = $policy['channel'] === 'SIPLAH';
        $add = function (string $label, string $source, bool $available, bool $applicable = true, ?string $note = null) use (&$documents): void {
            $documents[] = (object) [
                'label' => $label,
                'source' => $source,
                'status' => ! $applicable ? 'TIDAK BERLAKU' : ($available ? 'TERSEDIA' : 'BELUM LENGKAP'),
                'note' => $note,
            ];
        };

        $add(
            'Jejak RKAS dan BKU',
            'ARKAS/BKU',
            $reconciliation !== null && ! in_array($reconciliation->status, ['BKU TANPA TRANSAKSI', 'TRANSAKSI TANPA BKU'], true),
            true,
            $reconciliation ? 'Status rekonsiliasi: '.$reconciliation->status : 'Nomor bukti belum terhubung pada rekonsiliasi.'
        );
        $add('Rincian transaksi', 'Transaksi', $transaction->items->isNotEmpty());
        $add('Paket SPJ', 'Paket SPJ', (bool) $transaction->spjPackage, true, $transaction->spjPackage?->status ? 'Status paket: '.$transaction->spjPackage->status : null);
        $add('Nomor paket SPJ', 'Penomoran', filled($transaction->spjPackage?->document_number));
        $add(
            'Kuitansi / Bukti Kas Pengeluaran (A2)',
            'Wajib dari aplikasi',
            filled($transaction->effective_receipt_recipient_name)
                && filled($transaction->payment_description ?: $transaction->description)
                && (float) $transaction->gross_amount > 0,
            true,
            'Wajib untuk transaksi SIPLah maupun non-SIPLah dan harus masuk paket cetak SPJ.'
        );
        $add('Bukti pembayaran / referensi bayar', $isSiplah ? 'SIPLah/Bank' : 'Pembayaran', $transaction->payments->isNotEmpty() || filled($transaction->payment_reference));

        if ($isSiplah) {
            $add('Referensi pesanan SIPLah', 'SIPLah', filled($transaction->payment_reference) || filled($transaction->invoice_number), true, 'Gunakan nomor pesanan atau invoice dari SIPLah sebagai jejak pengadaan.');
            $add('Identitas penyedia SIPLah', 'SIPLah', filled($transaction->vendor_name));
            $add('Invoice/tagihan SIPLah', 'SIPLah', filled($transaction->invoice_number));
        }

        $add(
            'Bukti pajak / setor pajak',
            'Pajak',
            false,
            (float) $transaction->tax_total > 0,
            (float) $transaction->tax_total > 0 ? 'Nilai pajak tercatat; bukti setor tetap perlu dicocokkan dengan dokumen sumber.' : 'Transaksi tidak memiliki pajak tercatat.'
        );

        $category = strtoupper((string) $transaction->spj_category);
        $goodsCategory = in_array($category, ['BARANG', 'BELANJA_MODAL', 'KONSUMSI'], true);
        $firstGoods = $transaction->goods->first();
        $add(
            $isSiplah ? 'Pesanan dari SIPLah' : 'Surat pesanan',
            $isSiplah ? 'SIPLah' : 'Belanja barang',
            $isSiplah ? (filled($transaction->payment_reference) || filled($transaction->invoice_number)) : (filled($firstGoods?->order_number) && filled($firstGoods?->order_date)),
            $goodsCategory,
            $isSiplah ? 'Tidak perlu membuat ulang surat pesanan internal jika dokumen pesanan SIPLah sudah menjadi bukti sumber.' : null
        );
        $add('Faktur / invoice', $isSiplah ? 'SIPLah/Penyedia' : 'Belanja barang', filled($transaction->invoice_number), $goodsCategory);
        $add('Berita acara pemeriksaan/penerimaan', 'Belanja barang', filled($firstGoods?->bap_number) && filled($firstGoods?->bap_date), $goodsCategory);
        $add('Berita acara serah terima', 'Belanja barang', filled($firstGoods?->bast_number) && filled($firstGoods?->bast_date), $goodsCategory);
        $add('Penerimaan barang tercatat', 'Belanja barang', $transaction->goodsReceipts->isNotEmpty(), $goodsCategory);

        $workCategory = in_array($category, ['PEMELIHARAAN', 'UPAH'], true);
        $add('RAB pekerjaan', 'Pemeliharaan', filled($transaction->workOrder?->rab_number) && filled($transaction->workOrder?->rab_date), $workCategory);
        $add('SPK pekerjaan', 'Pemeliharaan', filled($transaction->workOrder?->spk_number) && filled($transaction->workOrder?->spk_date), $workCategory);
        $add('Daftar pekerja/upah', 'Pemeliharaan', $transaction->workers->isNotEmpty(), $workCategory);

        $travelCategory = in_array($category, ['SPPD', 'PERJALANAN_DINAS'], true);
        $add('Rincian perjalanan dinas', 'Perjalanan dinas', $transaction->travels->isNotEmpty(), $travelCategory);

        $honorCategory = in_array($category, ['HONOR_PEGAWAI', 'JASA_HONORARIUM'], true);
        $add('Daftar penerima honor', 'Honorarium', $transaction->honors->isNotEmpty(), $honorCategory);

        return $documents;
    }
}
