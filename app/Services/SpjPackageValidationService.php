<?php

namespace App\Services;

use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SpjPackageValidationService
{
    public function __construct(
        private SpjProcurementPolicyService $procurementPolicy,
        private SpjDocumentRequirementService $documentRequirements,
    ) {}

    /**
     * @return array<int,array{key:string,group:string,label:string,passed:bool,message:string,url:string}>
     */
    public function checklist(SpjPackage $package): array
    {
        $transaction = $package->transaction;
        $transactionUrl = route('transactions.show', $transaction->id);
        $packageUrl = route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]).'#spj-manual-form';
        $checks = [];
        $policy = $this->procurementPolicy->forTransaction($transaction);
        $channel = $policy['channel_label'];

        $this->addCheck($checks, 'procurement_channel', 'Cara pengadaan', 'Jalur transaksi', true, 'Transaksi dikenali sebagai '.$channel.'.', 'Jalur transaksi belum dapat dikenali.', $packageUrl);
        $this->addCheck($checks, 'spj_category', 'Data Umum Dokumen', 'Kategori SPJ', filled($transaction->spj_category), 'Kategori SPJ sudah tersedia.', 'Kategori SPJ wajib dipilih sebelum penomoran.', $packageUrl);
        $this->addCheck($checks, 'payment_description', 'Data Umum Dokumen', 'Uraian pembayaran', filled($transaction->payment_description), 'Uraian pembayaran sudah tersedia.', 'Uraian pembayaran wajib diisi sebelum penomoran.', $packageUrl);
        $this->addCheck($checks, 'recipient', 'Data Umum Dokumen', 'Penerima kuitansi', filled($transaction->effective_receipt_recipient_name), 'Penerima kuitansi sudah tersedia.', 'Penerima kuitansi wajib diisi sebelum penomoran.', $packageUrl);
        $this->addCheck($checks, 'payment_method', 'Data Umum Dokumen', 'Cara bayar', filled($transaction->payment_method), 'Cara bayar sudah dipilih.', 'Cara bayar wajib dipilih sebelum penomoran.', $packageUrl);
        $this->addCheck($checks, 'rkas_date', 'Sumber RKAS', 'Tanggal RKAS', $transaction->rkas_date !== null, 'Tanggal RKAS sumber sudah tersedia.', 'Tanggal RKAS belum tersedia. Sinkronkan ulang ARKAS dengan Bridge terbaru sebelum penomoran.', $transactionUrl);

        $orderDates = $transaction->goods->pluck('order_date')->filter();
        $rkasPeriodDate = $this->rkasPeriodDate($transaction);
        $rkasDateForValidation = $rkasPeriodDate ?: $transaction->rkas_date;
        $orderMonthValid = $rkasDateForValidation === null || $orderDates->isEmpty() || $orderDates->every(function ($date) use ($rkasDateForValidation): bool {
            $rkasMonth = Carbon::parse($rkasDateForValidation)->startOfMonth();
            $orderMonth = Carbon::parse($date)->startOfMonth();

            return $orderMonth->greaterThanOrEqualTo($rkasMonth);
        });
        $this->addCheck(
            $checks,
            'order_month_after_rkas',
            'Sumber RKAS',
            'Bulan Pesanan ≥ Bulan RKAS',
            $orderMonthValid,
            'Bulan Pesanan tidak lebih awal dari bulan RKAS.',
            'Bulan Pesanan tidak boleh lebih awal dari bulan RKAS. Koreksi Tanggal Pesanan sebelum penomoran.',
            $packageUrl,
        );

        $this->addCheck($checks, 'activity_code', 'Umum', 'Kode kegiatan', filled($transaction->activity_code), 'Kode kegiatan sudah tersedia.', 'Kode kegiatan belum tersedia.', $transactionUrl);
        $this->addCheck($checks, 'activity_name', 'Umum', 'Nama kegiatan', filled($transaction->activity_name), 'Nama kegiatan sudah tersedia.', 'Nama kegiatan belum tersedia.', $transactionUrl);
        $this->addCheck($checks, 'account_code', 'Umum', 'Kode rekening', filled($transaction->account_code), 'Kode rekening sudah tersedia.', 'Kode rekening belum tersedia.', $transactionUrl);
        $this->addCheck($checks, 'account_name', 'Umum', 'Nama rekening', filled($transaction->account_name), 'Nama rekening sudah tersedia.', 'Nama rekening belum tersedia.', $transactionUrl);

        $a2Ready = filled($transaction->effective_receipt_recipient_name)
            && filled($transaction->payment_description ?: $transaction->description)
            && (float) $transaction->gross_amount > 0;
        $this->addCheck(
            $checks,
            'receipt_a2',
            'Dokumen wajib',
            'Kuitansi / Bukti Kas Pengeluaran (A2)',
            $a2Ready,
            'Data Kuitansi/A2 lengkap dan wajib dicetak untuk transaksi '.$channel.'.',
            'Kuitansi/A2 wajib untuk transaksi '.$channel.'. Lengkapi penerima, uraian pembayaran, dan nilai transaksi agar dapat dicetak.',
            $packageUrl
        );

        $hasDetails = $transaction->items->isNotEmpty() || $transaction->goods->isNotEmpty();
        $this->addCheck($checks, 'details', 'Rincian transaksi', 'Rincian barang/jasa', $hasDetails, 'Transaksi memiliki rincian barang/jasa.', 'Transaksi belum memiliki rincian barang atau jasa.', $transactionUrl.'#rincian-transaksi');

        $grossAmount = (float) $transaction->gross_amount;
        $itemTotal = (float) $transaction->items->sum('amount');
        $itemTotalMatches = $transaction->items->isEmpty() || abs($itemTotal - $grossAmount) <= 0.01;
        $itemTotalMessage = $itemTotalMatches
            ? 'Total rincian sesuai dengan nilai bruto transaksi.'
            : sprintf('Total rincian Rp %s tidak sama dengan nilai bruto Rp %s.', number_format($itemTotal, 0, ',', '.'), number_format($grossAmount, 0, ',', '.'));
        $this->addCheck($checks, 'item_total', 'Rincian transaksi', 'Total rincian transaksi', $itemTotalMatches, $itemTotalMessage, $itemTotalMessage, $transactionUrl.'#rincian-transaksi');

        $category = strtoupper((string) $transaction->spj_category);

        if ($category === 'BARANG') {
            $hasItems = $transaction->items->isNotEmpty();
            $this->addCheck($checks, 'goods_items', 'Belanja barang', 'Barang pesanan', $hasItems, 'Pesanan memiliki rincian barang.', 'Pesanan wajib memiliki minimal satu barang.', $transactionUrl.'#rincian-transaksi');

            $incompleteItems = $transaction->items->filter(fn ($item) => blank($item->item_description) || (float) $item->quantity <= 0 || (float) $item->unit_price < 0);
            $itemsComplete = $hasItems && $incompleteItems->isEmpty();
            $this->addCheck($checks, 'goods_completeness', 'Belanja barang', 'Kelengkapan barang', $itemsComplete, 'Uraian, jumlah, dan harga setiap barang sudah lengkap.', 'Setiap barang wajib memiliki uraian, jumlah lebih dari nol, dan harga yang valid.', $transactionUrl.'#rincian-transaksi');

            $invalidAmounts = $transaction->items->filter(fn ($item) => abs(((float) $item->quantity * (float) $item->unit_price) - (float) $item->amount) > 0.01);
            $amountsValid = $hasItems && $invalidAmounts->isEmpty();
            $invalidNames = $invalidAmounts->map(fn ($item) => $item->item_description ?: $item->description ?: '#'.$item->id)->implode(', ');
            $this->addCheck(
                $checks,
                'goods_amounts',
                'Belanja barang',
                'Nilai item barang',
                $amountsValid,
                'Perhitungan jumlah × harga setiap barang sudah konsisten.',
                $invalidNames !== '' ? 'Jumlah × harga tidak sama dengan nilai item: '.$invalidNames.'.' : 'Perhitungan nilai item barang belum lengkap.',
                $transactionUrl.'#rincian-transaksi'
            );

            if ($policy['channel'] === 'SIPLAH') {
                $hasSiplahMetadata = filled($transaction->payment_reference)
                    && filled($transaction->invoice_number)
                    && $transaction->invoice_date !== null;
                $this->addCheck($checks, 'siplah_metadata', 'Belanja barang', 'Data transaksi SiPLah', $hasSiplahMetadata, 'Referensi pembayaran serta nomor dan tanggal invoice SiPLah sudah tersedia.', 'Transaksi SiPLah memerlukan referensi pembayaran, nomor invoice, dan tanggal invoice.', $packageUrl);
            } else {
                $this->addCheck($checks, 'goods_documents', 'Belanja barang', 'Dokumen barang', $transaction->goods->isNotEmpty(), 'Data pesanan/BAP/BAST sudah dibuat.', 'Data pesanan, BAP, dan BAST belum dibuat.', $packageUrl);
            }

            $hasBapOrBast = $transaction->goods->contains(fn ($goods) => filled($goods->bap_number) || filled($goods->bap_date) || filled($goods->bast_number) || filled($goods->bast_date));
            $bapBastReady = ! $hasBapOrBast || ($itemsComplete && $amountsValid);
            $this->addCheck($checks, 'goods_bap_bast', 'Belanja barang', 'Kelengkapan BAP/BAST', $bapBastReady, 'Rincian barang konsisten untuk BAP/BAST.', 'BAP dan BAST belum dapat diterbitkan sebelum rincian barang lengkap dan konsisten.', $packageUrl);

            $duplicateExists = false;
            if (filled($transaction->invoice_number) && filled($transaction->vendor_name)) {
                $duplicateExists = $transaction->newQuery()
                    ->where('fiscal_year_id', $transaction->fiscal_year_id)
                    ->whereKeyNot($transaction->id)
                    ->whereRaw('LOWER(TRIM(invoice_number)) = ?', [mb_strtolower(trim((string) $transaction->invoice_number))])
                    ->whereRaw('LOWER(TRIM(vendor_name)) = ?', [mb_strtolower(trim((string) $transaction->vendor_name))])
                    ->exists();
            }
            $this->addCheck($checks, 'invoice_duplicate', 'Belanja barang', 'Keunikan invoice', ! $duplicateExists, 'Nomor invoice tidak terdeteksi sebagai duplikat.', 'Nomor invoice ini sudah digunakan oleh vendor yang sama pada tahun anggaran aktif.', $packageUrl);
        }

        if ($category === 'HONOR_PEGAWAI') {
            $hasHonors = $transaction->honors->isNotEmpty();
            $this->addCheck($checks, 'honor_recipients', 'Honor pegawai', 'Rincian penerima honorarium', $hasHonors, 'Daftar penerima honorarium sudah tersedia.', 'Honor Pegawai memerlukan minimal satu penerima.', $packageUrl);

            $honorTotal = (float) $transaction->honors->sum('gross_amount');
            $honorTotalMatches = $hasHonors && abs($honorTotal - $grossAmount) <= 0.01;
            $honorMessage = $honorTotalMatches
                ? 'Total honor sesuai dengan nilai bruto transaksi.'
                : sprintf('Total honor Rp %s tidak sama dengan nilai bruto Rp %s.', number_format($honorTotal, 0, ',', '.'), number_format($grossAmount, 0, ',', '.'));
            $this->addCheck($checks, 'honor_total', 'Honor pegawai', 'Total honor', $honorTotalMatches, $honorMessage, $honorMessage, $packageUrl);
        }

        if ($category === 'JASA_LAINNYA') {
            $recipients = $transaction->serviceRecipients;
            $hasRecipients = $recipients->isNotEmpty();
            $this->addCheck($checks, 'service_recipients', 'Jasa lainnya', 'Daftar penerima pembayaran', $hasRecipients, 'Daftar penerima jasa sudah tersedia.', 'Jasa Lainnya memerlukan minimal satu penerima pembayaran.', $packageUrl);

            $grossTotal = (float) $recipients->sum('amount');
            $grossMatches = $hasRecipients && abs($grossTotal - $grossAmount) <= 0.01;
            $grossMessage = $grossMatches ? 'Total penerima jasa sesuai dengan nilai bruto transaksi.' : sprintf('Total penerima jasa Rp %s tidak sama dengan nilai bruto Rp %s.', number_format($grossTotal, 0, ',', '.'), number_format($grossAmount, 0, ',', '.'));
            $this->addCheck($checks, 'service_recipient_total', 'Jasa lainnya', 'Total bruto penerima', $grossMatches, $grossMessage, $grossMessage, $packageUrl);

            $taxTotal = (float) $recipients->sum('tax_amount');
            $sourceTax = (float) $transaction->tax_total;
            $taxMatches = $hasRecipients && abs($taxTotal - $sourceTax) <= 0.01;
            $taxMessage = $taxMatches ? 'Total pajak penerima sesuai dengan pajak transaksi.' : sprintf('Total pajak penerima Rp %s tidak sama dengan pajak transaksi Rp %s.', number_format($taxTotal, 0, ',', '.'), number_format($sourceTax, 0, ',', '.'));
            $this->addCheck($checks, 'service_recipient_tax_total', 'Jasa lainnya', 'Total pajak penerima', $taxMatches, $taxMessage, $taxMessage, $packageUrl);

            $netTotal = (float) $recipients->sum('net_amount');
            $sourceNet = (float) $transaction->net_amount;
            $netMatches = $hasRecipients && abs($netTotal - $sourceNet) <= 0.01;
            $netMessage = $netMatches ? 'Total netto penerima sesuai dengan netto transaksi.' : sprintf('Total netto penerima Rp %s tidak sama dengan netto transaksi Rp %s.', number_format($netTotal, 0, ',', '.'), number_format($sourceNet, 0, ',', '.'));
            $this->addCheck($checks, 'service_recipient_net_total', 'Jasa lainnya', 'Total netto penerima', $netMatches, $netMessage, $netMessage, $packageUrl);

            $lineTotalsValid = $hasRecipients && $recipients->every(fn ($recipient): bool => abs(((float) $recipient->amount - (float) $recipient->tax_amount) - (float) $recipient->net_amount) <= 0.01);
            $this->addCheck($checks, 'service_recipient_line_totals', 'Jasa lainnya', 'Bruto/pajak/netto per penerima', $lineTotalsValid, 'Setiap penerima memiliki bruto, pajak, dan netto yang konsisten.', 'Ada penerima jasa dengan bruto, pajak, dan netto yang belum konsisten. Simpan ulang rincian penerima untuk melakukan rekonsiliasi.', $packageUrl);
        }

        $alreadyCovered = ['a2', 'transaction_details', 'siplah_order', 'vendor', 'goods_receipt', 'honor'];
        foreach ($this->documentRequirements->forTransaction($transaction) as $requirement) {
            if (! $requirement['applicable'] || ! $requirement['required'] || in_array($requirement['key'], $alreadyCovered, true)) {
                continue;
            }

            $this->addCheck(
                $checks,
                'document_'.$requirement['key'],
                'Dokumen wajib · '.$requirement['group'],
                $requirement['label'],
                $requirement['available'],
                $requirement['message'],
                $requirement['message'],
                $packageUrl
            );
        }

        return $checks;
    }

    private function rkasPeriodDate(Transaction $transaction): ?Carbon
    {
        $sourceRapbsIds = DB::connection('school')->table('arkas_bku_rows')
            ->where('fiscal_year_id', $transaction->fiscal_year_id)
            ->when(filled($transaction->id_kas_umum), fn ($query) => $query->where('source_kas_id', $transaction->id_kas_umum))
            ->when(blank($transaction->id_kas_umum), fn ($query) => $query->where('no_bukti', $transaction->no_bukti))
            ->pluck('payload')
            ->flatMap(function ($payload): array {
                $row = is_array($payload) ? $payload : json_decode((string) $payload, true);

                return is_array($row) && filled($row['ID_RAPBS'] ?? null) ? [(string) $row['ID_RAPBS']] : [];
            })
            ->unique()
            ->values()
            ->all();

        if ($sourceRapbsIds === []) {
            return null;
        }

        $periodQuery = DB::connection('school')->table('arkas_rkas_periods')
            ->where('fiscal_year_id', $transaction->fiscal_year_id)
            ->whereIn('source_rapbs_id', $sourceRapbsIds)
            ->whereNotNull('month_number');
        $month = (clone $periodQuery)->where('amount', '>', 0)->min('month_number')
            ?: $periodQuery->min('month_number');

        if (! $month) {
            return null;
        }

        $year = ($transaction->transaction_date ?: $transaction->rkas_date)?->year ?? now()->year;

        return Carbon::create($year, (int) $month, 1)->startOfMonth();
    }

    /** @return array<int,array{label:string,message:string,url:string}> */
    public function validate(SpjPackage $package): array
    {
        return collect($this->checklist($package))
            ->where('passed', false)
            ->map(fn (array $check) => [
                'label' => $check['label'],
                'message' => $check['message'],
                'url' => $check['url'],
            ])
            ->values()
            ->all();
    }

    /**
     * Validate the source data before a numbering run. Document numbers that
     * the same run will issue must not prevent that run from starting.
     *
     * @return array<int,array{label:string,message:string,url:string}>
     */
    public function validateForNumbering(SpjPackage $package): array
    {
        return collect($this->checklist($package))
            ->reject(fn (array $check): bool => $check['key'] === 'document_internal_order_number')
            ->where('passed', false)
            ->map(fn (array $check) => [
                'label' => $check['label'],
                'message' => $check['message'],
                'url' => $check['url'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array{key:string,group:string,label:string,passed:bool,message:string,url:string}>  $checks
     */
    private function addCheck(array &$checks, string $key, string $group, string $label, bool $passed, string $passedMessage, string $failedMessage, string $url): void
    {
        $checks[] = [
            'key' => $key,
            'group' => $group,
            'label' => $label,
            'passed' => $passed,
            'message' => $passed ? $passedMessage : $failedMessage,
            'url' => $url,
        ];
    }
}
