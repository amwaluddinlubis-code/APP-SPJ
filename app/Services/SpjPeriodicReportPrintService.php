<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use App\UseCases\Spj\SpjPeriodicReportUseCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SpjPeriodicReportPrintService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjPeriodicReportUseCase $reports,
    ) {}

    /** @return array<string,mixed>|null */
    public function build(string $scope, string $reportKey, ?int $period): ?array
    {
        $payload = $this->reports->payload($scope, $reportKey, $period);

        if ($payload === null || ! $payload['summary']['ready']) {
            return null;
        }

        /** @var Collection<int,Transaction> $transactions */
        $transactions = $payload['transactions'];
        $school = $this->context->school();
        $year = FiscalYear::query()->with('fundSource')->findOrFail($this->context->fiscalYearId());
        $profile = DB::connection('school')
            ->table('school_profiles')
            ->where('fiscal_year_id', $year->id)
            ->first();

        $presentation = $this->presentation($reportKey);
        [$columns, $rows] = $this->table($presentation, $transactions);
        $summary = $payload['summary'];

        return [
            ...$payload,
            'school' => $school,
            'year' => $year,
            'profile' => $profile,
            'fundSource' => $year->fundSource?->name ?? $year->fund_source ?? '-',
            'presentation' => $presentation,
            'columns' => $columns,
            'rows' => $rows,
            'statement' => $this->statement($reportKey, (string) $summary['period_label']),
            'orientation' => $this->orientation($presentation),
            'fileName' => $this->fileName($payload['report']['label'], (string) $summary['period_label']),
            'generatedAt' => now(),
        ];
    }

    private function presentation(string $reportKey): string
    {
        return match ($reportKey) {
            'bku' => 'ledger',
            'buku_pembantu_kas' => 'cash_ledger',
            'buku_pembantu_bank' => 'bank_ledger',
            'buku_pembantu_pajak' => 'tax',
            'bos_k7a', 'bos_k8' => 'activity_summary',
            'format_k7', 'rekap_belanja_modal_barang_jasa', 'rekap_bmd',
            'rekap_belanja_dana_bos', 'form_1c', 'rekapitulasi_pengeluaran_dana_bos' => 'account_summary',
            'lampiran_sp2b', 'lampiran_berita_acara_rekonsiliasi' => 'transaction_recap',
            default => 'statement',
        };
    }

    /** @return array{0:list<array{key:string,label:string,type:string}>,1:list<array<string,mixed>>} */
    private function table(string $presentation, Collection $transactions): array
    {
        return match ($presentation) {
            'ledger' => [$this->ledgerColumns(), $this->ledgerRows($transactions)],
            'cash_ledger' => [$this->ledgerColumns(), $this->ledgerRows($this->cashTransactions($transactions))],
            'bank_ledger' => [$this->ledgerColumns(), $this->ledgerRows($this->bankTransactions($transactions))],
            'tax' => [$this->taxColumns(), $this->taxRows($transactions)],
            'activity_summary' => [$this->activityColumns(), $this->activityRows($transactions)],
            'account_summary' => [$this->accountColumns(), $this->accountRows($transactions)],
            'transaction_recap' => [$this->recapColumns(), $this->recapRows($transactions)],
            default => [$this->accountColumns(), $this->accountRows($transactions)],
        };
    }

    /** @return Collection<int,Transaction> */
    private function cashTransactions(Collection $transactions): Collection
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => $transaction->payment_method === 'tunai')
            ->values();
    }

    /** @return Collection<int,Transaction> */
    private function bankTransactions(Collection $transactions): Collection
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => in_array($transaction->payment_method, ['transfer_bank', 'siplah'], true))
            ->values();
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function ledgerColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'type' => 'integer'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'text'],
            ['key' => 'evidence', 'label' => 'No. Bukti', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Uraian', 'type' => 'text'],
            ['key' => 'account', 'label' => 'Rekening', 'type' => 'text'],
            ['key' => 'recipient', 'label' => 'Penerima', 'type' => 'text'],
            ['key' => 'gross', 'label' => 'Pengeluaran Bruto', 'type' => 'money'],
            ['key' => 'tax', 'label' => 'Pajak', 'type' => 'money'],
            ['key' => 'net', 'label' => 'Dibayarkan', 'type' => 'money'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function ledgerRows(Collection $transactions): array
    {
        return $transactions->values()->map(fn (Transaction $transaction, int $index): array => [
            'no' => $index + 1,
            'date' => $transaction->transaction_date?->format('d-m-Y') ?? '-',
            'evidence' => $transaction->no_bukti ?: '-',
            'description' => $transaction->description ?: '-',
            'account' => trim(($transaction->account_code ?: '').' '.($transaction->account_name ?: '')) ?: '-',
            'recipient' => $transaction->recipient_name ?: '-',
            'gross' => (float) $transaction->gross_amount,
            'tax' => (float) $transaction->tax_total,
            'net' => (float) $transaction->net_amount,
        ])->all();
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function taxColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'type' => 'integer'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'text'],
            ['key' => 'evidence', 'label' => 'No. Bukti', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Uraian', 'type' => 'text'],
            ['key' => 'ppn', 'label' => 'PPN', 'type' => 'money'],
            ['key' => 'pph21', 'label' => 'PPh 21', 'type' => 'money'],
            ['key' => 'pph22', 'label' => 'PPh 22', 'type' => 'money'],
            ['key' => 'pph23', 'label' => 'PPh 23', 'type' => 'money'],
            ['key' => 'pph4', 'label' => 'PPh 4(2)', 'type' => 'money'],
            ['key' => 'sspd', 'label' => 'SSPD', 'type' => 'money'],
            ['key' => 'tax', 'label' => 'Total Pajak', 'type' => 'money'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function taxRows(Collection $transactions): array
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => (float) $transaction->tax_total > 0)
            ->values()
            ->map(fn (Transaction $transaction, int $index): array => [
                'no' => $index + 1,
                'date' => $transaction->transaction_date?->format('d-m-Y') ?? '-',
                'evidence' => $transaction->no_bukti ?: '-',
                'description' => $transaction->description ?: '-',
                'ppn' => (float) $transaction->ppn,
                'pph21' => (float) $transaction->pph21,
                'pph22' => (float) $transaction->pph22,
                'pph23' => (float) $transaction->pph23,
                'pph4' => (float) $transaction->pph4,
                'sspd' => (float) $transaction->sspd,
                'tax' => (float) $transaction->tax_total,
            ])->all();
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function activityColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'type' => 'integer'],
            ['key' => 'code', 'label' => 'Kode Kegiatan', 'type' => 'text'],
            ['key' => 'name', 'label' => 'Kegiatan', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Transaksi', 'type' => 'integer'],
            ['key' => 'gross', 'label' => 'Bruto', 'type' => 'money'],
            ['key' => 'tax', 'label' => 'Pajak', 'type' => 'money'],
            ['key' => 'net', 'label' => 'Realisasi Netto', 'type' => 'money'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function activityRows(Collection $transactions): array
    {
        return $transactions
            ->groupBy(fn (Transaction $transaction): string => ($transaction->activity_code ?: '-').'|'.($transaction->activity_name ?: '-'))
            ->values()
            ->map(function (Collection $group, int $index): array {
                /** @var Transaction $first */
                $first = $group->first();

                return [
                    'no' => $index + 1,
                    'code' => $first->activity_code ?: '-',
                    'name' => $first->activity_name ?: '-',
                    'count' => $group->count(),
                    'gross' => (float) $group->sum('gross_amount'),
                    'tax' => (float) $group->sum('tax_total'),
                    'net' => (float) $group->sum('net_amount'),
                ];
            })
            ->sortBy('code')
            ->values()
            ->all();
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function accountColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'type' => 'integer'],
            ['key' => 'code', 'label' => 'Kode Rekening', 'type' => 'text'],
            ['key' => 'name', 'label' => 'Nama Rekening', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Transaksi', 'type' => 'integer'],
            ['key' => 'gross', 'label' => 'Bruto', 'type' => 'money'],
            ['key' => 'tax', 'label' => 'Pajak', 'type' => 'money'],
            ['key' => 'net', 'label' => 'Realisasi Netto', 'type' => 'money'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function accountRows(Collection $transactions): array
    {
        return $transactions
            ->groupBy(fn (Transaction $transaction): string => ($transaction->account_code ?: '-').'|'.($transaction->account_name ?: '-'))
            ->values()
            ->map(function (Collection $group, int $index): array {
                /** @var Transaction $first */
                $first = $group->first();

                return [
                    'no' => $index + 1,
                    'code' => $first->account_code ?: '-',
                    'name' => $first->account_name ?: '-',
                    'count' => $group->count(),
                    'gross' => (float) $group->sum('gross_amount'),
                    'tax' => (float) $group->sum('tax_total'),
                    'net' => (float) $group->sum('net_amount'),
                ];
            })
            ->sortBy('code')
            ->values()
            ->all();
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function recapColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'type' => 'integer'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'text'],
            ['key' => 'evidence', 'label' => 'No. Bukti', 'type' => 'text'],
            ['key' => 'activity', 'label' => 'Kegiatan', 'type' => 'text'],
            ['key' => 'account', 'label' => 'Rekening', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Uraian', 'type' => 'text'],
            ['key' => 'gross', 'label' => 'Bruto', 'type' => 'money'],
            ['key' => 'tax', 'label' => 'Pajak', 'type' => 'money'],
            ['key' => 'net', 'label' => 'Netto', 'type' => 'money'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function recapRows(Collection $transactions): array
    {
        return $transactions->values()->map(fn (Transaction $transaction, int $index): array => [
            'no' => $index + 1,
            'date' => $transaction->transaction_date?->format('d-m-Y') ?? '-',
            'evidence' => $transaction->no_bukti ?: '-',
            'activity' => trim(($transaction->activity_code ?: '').' '.($transaction->activity_name ?: '')) ?: '-',
            'account' => trim(($transaction->account_code ?: '').' '.($transaction->account_name ?: '')) ?: '-',
            'description' => $transaction->description ?: '-',
            'gross' => (float) $transaction->gross_amount,
            'tax' => (float) $transaction->tax_total,
            'net' => (float) $transaction->net_amount,
        ])->all();
    }

    /** @return list<string> */
    private function statement(string $reportKey, string $periodLabel): array
    {
        return match ($reportKey) {
            'sptjm' => [
                "Dengan ini menyatakan bahwa penggunaan dana pada {$periodLabel} telah dicatat berdasarkan transaksi pada konteks sekolah, tahun anggaran, dan sumber dana aktif.",
                'Seluruh bukti pengeluaran, pemotongan pajak, dan dokumen pendukung menjadi bagian yang tidak terpisahkan dari pertanggungjawaban periode ini.',
            ],
            'k7b' => [
                "Register penutupan kas {$periodLabel} merangkum transaksi, nilai bruto, pajak, dan nilai yang dibayarkan pada periode laporan.",
                'Saldo fisik kas dan bank tetap harus dicocokkan dengan rekening koran/buku kas yang dikuasai bendahara pada tanggal penutupan.',
            ],
            'k7c' => [
                "Pada akhir {$periodLabel} dilakukan pemeriksaan atas pencatatan transaksi dan pertanggungjawaban kas berdasarkan data yang tersedia pada APP-SPJ.",
                'Hasil pemeriksaan ditandatangani setelah nilai pada laporan ini dicocokkan dengan bukti fisik dan saldo aktual.',
            ],
            'spb' => [
                "SPB {$periodLabel} menyajikan ringkasan pengeluaran yang dipertanggungjawabkan pada periode laporan beserta rekap rekening belanjanya.",
            ],
            'sp2b' => [
                "SP2B {$periodLabel} merangkum nilai bruto, pajak, realisasi netto, dan rekap rekening transaksi pada periode aktif.",
            ],
            'sp2t' => [
                "SP2T {$periodLabel} menyajikan pertanggungjawaban transaksi dan pajak periode aktif untuk proses penatausahaan berikutnya.",
            ],
            'berita_acara_rekonsiliasi' => [
                "Berita acara rekonsiliasi {$periodLabel} dibuat berdasarkan pencocokan data transaksi, nilai bruto, pajak, dan realisasi netto pada konteks aktif.",
                'Lampiran rincian transaksi digunakan sebagai dasar penelusuran bila terdapat perbedaan dengan catatan eksternal.',
            ],
            default => [],
        };
    }

    private function orientation(string $presentation): string
    {
        return in_array($presentation, ['ledger', 'cash_ledger', 'bank_ledger', 'tax', 'transaction_recap'], true)
            ? 'landscape'
            : 'portrait';
    }

    private function fileName(string $label, string $periodLabel): string
    {
        $value = strtoupper($label.'-'.$periodLabel);
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value) ?: 'LAPORAN-PERIODE';

        return trim($value, '-');
    }
}
