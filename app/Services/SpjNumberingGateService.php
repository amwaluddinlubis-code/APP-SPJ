<?php

namespace App\Services;

use App\Models\FiscalPeriodClosure;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Carbon;

class SpjNumberingGateService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjNumberingPolicyService $numberingPolicy,
    ) {}

    public function previousQuarterFinalBlocker(int $quarter): ?string
    {
        if ($quarter <= 1) {
            return null;
        }

        $previousQuarter = $quarter - 1;
        $notFinal = Transaction::query()
            ->forSpjContext($this->context)
            ->whereMonth('transaction_date', '>=', (($previousQuarter - 1) * 3) + 1)
            ->whereMonth('transaction_date', '<=', $previousQuarter * 3)
            ->has('items')
            ->where(function ($query): void {
                $query->doesntHave('spjPackage')
                    ->orWhereHas('spjPackage', fn ($package) => $package->where('status', '!=', 'FINAL'));
            })
            ->count();

        if ($notFinal === 0) {
            return null;
        }

        return "Penomoran Triwulan {$quarter} dibatalkan: masih ada {$notFinal} transaksi Triwulan {$previousQuarter} yang paket SPJ-nya belum FINAL. Finalkan seluruh paket triwulan sebelumnya terlebih dahulu.";
    }

    public function periodBlocker(SpjPackage $package): ?string
    {
        if (! $package->relationLoaded('transaction')) {
            $package->load('transaction');
        }

        $transaction = $package->transaction;
        if (! $transaction || ! $this->context->matchesTransaction($transaction)) {
            return 'Paket tidak berada pada konteks sekolah, tahun anggaran, dan sumber dana aktif.';
        }
        if (! $transaction->transaction_date) {
            return 'Penomoran ditolak karena tanggal transaksi BKU belum tersedia.';
        }

        $quarter = (int) ceil((int) Carbon::parse($transaction->transaction_date)->format('n') / 3);
        if ($blocker = $this->previousQuarterFinalBlocker($quarter)) {
            return $blocker;
        }

        $period = FiscalPeriodClosure::query()
            ->where('fiscal_year_id', $this->context->fiscalYearId())
            ->where('quarter', $quarter)
            ->first();
        if ($period?->status === 'CLOSED') {
            return 'Triwulan sudah ditutup. Administrator harus membuka kembali periode terlebih dahulu.';
        }

        return null;
    }

    public function issuanceBlocker(SpjPackage $package, ?string $documentType = null): ?string
    {
        if ($blocker = $this->periodBlocker($package)) {
            return $blocker;
        }

        if (! in_array($package->status, ['READY', 'NUMBERED'], true)) {
            return 'Penomoran hanya dapat dilakukan pada paket READY atau NUMBERED. Selesaikan validasi paket terlebih dahulu.';
        }

        if ($documentType !== null) {
            $canonicalType = $this->numberingPolicy->canonicalAutomaticDocumentType($documentType);
            if ($canonicalType === null) {
                return 'Jenis dokumen tidak termasuk '.count($this->numberingPolicy->automaticDocumentTypes()).' domain penomoran canonical aplikasi.';
            }

            if (! $this->numberingPolicy->isAutomaticDocumentEligible($package->transaction, $canonicalType)) {
                $category = $this->numberingPolicy->canonicalCategory((string) $package->transaction->spj_category) ?: '-';
                $label = $this->numberingPolicy->automaticDocumentLabels()[$canonicalType] ?? $canonicalType;

                return 'Penomoran '.$label.' tidak berlaku untuk kategori '.$category.'.';
            }
        }

        return null;
    }
}
