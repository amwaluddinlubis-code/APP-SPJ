<?php

namespace App\Services;

use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SpjNumberingOrderService
{
    /**
     * @param  Collection<int, SpjPackage>  $packages
     * @return Collection<int, SpjPackage>
     */
    public function orderedPackagesForDocumentType(Collection $packages, string $documentType): Collection
    {
        return $packages
            ->sortBy(fn (SpjPackage $package): string => $this->numberingOrderKey($package, $documentType))
            ->values();
    }

    public function documentEventDate(SpjPackage $package, string $documentType): Carbon
    {
        $date = $this->documentEventDateValue($package, $documentType)
            ?? $package->transaction->transaction_date
            ?? now();

        return Carbon::parse($date);
    }

    public function documentEventDateValue(SpjPackage $package, string $documentType): mixed
    {
        $transaction = $package->transaction;

        return match (strtoupper(trim($documentType))) {
            'ORDER', 'PESANAN', 'SURAT_PESANAN' => $transaction->goods->pluck('order_date')->filter()->sort()->first(),
            'BAP' => $transaction->goods->pluck('bap_date')->filter()->sort()->first(),
            'BAST', 'RECEIPT', 'PENERIMAAN' => $transaction->goods->pluck('bast_date')->filter()->sort()->first(),
            'SPK', 'WORK_ORDER' => $transaction->workOrder?->spk_date,
            'RAB' => $transaction->workOrder?->rab_date,
            'SURAT_TUGAS_PERJALANAN_DINAS' => $transaction->travels->pluck('assignment_letter_date')->filter()->sort()->first()
                ?: $transaction->travels->pluck('departure_date')->filter()->sort()->first(),
            'SPPD' => $transaction->travels->pluck('departure_date')->filter()->sort()->first(),
            default => $transaction->transaction_date,
        };
    }

    public function sourceOrderKey(Transaction $transaction): string
    {
        $arkasTimestampKey = $this->arkasTimestampOrderKey($transaction);
        $sourceItemIds = $transaction->relationLoaded('items')
            ? $transaction->items->pluck('source_item_id')
            : $transaction->items()->pluck('source_item_id');
        $firstSourceItemId = $sourceItemIds
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => trim((string) $value))
            ->sortBy(fn (string $value): string => $this->normalizeSourceOrderPart($value))
            ->first();

        if (filled($firstSourceItemId)) {
            return implode('|', [
                $arkasTimestampKey,
                $this->normalizeSourceOrderPart($firstSourceItemId),
                $this->normalizeSourceOrderPart($transaction->source_key),
                $this->normalizeSourceOrderPart($transaction->no_bukti),
            ]);
        }

        if (filled($transaction->id_kas_umum)) {
            return implode('|', [
                $arkasTimestampKey,
                $this->normalizeSourceOrderPart($transaction->id_kas_umum),
                $this->normalizeSourceOrderPart($transaction->source_key),
                $this->normalizeSourceOrderPart($transaction->no_bukti),
            ]);
        }

        if (filled($transaction->source_key)) {
            return implode('|', [
                $arkasTimestampKey,
                $this->normalizeSourceOrderPart($transaction->source_key),
                $this->normalizeSourceOrderPart($transaction->no_bukti),
            ]);
        }

        return $arkasTimestampKey.'|'.$this->normalizeSourceOrderPart($transaction->no_bukti)
            .'|LOCAL:'.str_pad((string) $transaction->id, 20, '0', STR_PAD_LEFT);
    }

    /**
     * Single-document issuance stays available, but it may not jump over an
     * earlier unnumbered source transaction in the same quarter/domain.
     *
     * @param  array<int, string>  $documentTypes
     */
    public function singleNumberingBlocker(SpjPackage $package, array $documentTypes): ?string
    {
        $transactionDate = $package->transaction->transaction_date;
        if (! $transactionDate) {
            return 'Penomoran satuan ditolak karena tanggal transaksi BKU belum tersedia. Gunakan penomoran triwulan setelah data sumber lengkap.';
        }

        $month = (int) Carbon::parse($transactionDate)->format('n');
        $quarter = (int) ceil($month / 3);
        $startMonth = (($quarter - 1) * 3) + 1;
        $endMonth = $quarter * 3;
        $candidates = SpjPackage::query()
            ->with([
                'documents',
                'transaction.items',
                'transaction.goods',
                'transaction.goodsReceipts',
                'transaction.workOrder',
                'transaction.honors',
                'transaction.travels',
                'transaction.payments',
                'transaction.workers',
                'transaction.participants',
                'transaction.serviceRecipients',
                'transaction.spjPackage',
            ])
            ->whereHas('transaction', fn ($query) => $query->activeContext()
                ->whereMonth('transaction_date', '>=', $startMonth)
                ->whereMonth('transaction_date', '<=', $endMonth))
            ->whereIn('status', ['DRAFT', 'READY', 'NUMBERED', 'DICETAK', 'CANCELLED'])
            ->get();

        foreach ($documentTypes as $documentType) {
            if ($this->documentEventDateValue($package, $documentType) === null) {
                continue;
            }

            $eligible = $candidates->filter(fn (SpjPackage $candidate): bool => $this->documentEventDateValue($candidate, $documentType) !== null);
            foreach ($this->orderedPackagesForDocumentType($eligible, $documentType) as $candidate) {
                if ($candidate->is($package)) {
                    break;
                }
                if ($this->needsAutomaticNumber($candidate, $documentType)) {
                    return 'Penomoran satuan ditolak agar urutan '.$documentType.' tetap selaras dengan BKU. Transaksi lebih awal (bukti '.$candidate->transaction->no_bukti.') belum bernomor. Gunakan penomoran triwulan atau nomor transaksi yang lebih awal terlebih dahulu.';
                }
            }
        }

        return null;
    }

    private function numberingOrderKey(SpjPackage $package, string $documentType): string
    {
        $date = $this->documentEventDate($package, $documentType)->format('Y-m-d');

        return $date.'|'.$this->sourceOrderKey($package->transaction);
    }

    private function arkasTimestampOrderKey(Transaction $transaction): string
    {
        $createdAt = $transaction->source_created_at?->format('Y-m-d H:i:s.u') ?? '9999-12-31 23:59:59.999999';
        $lastUpdatedAt = $transaction->source_last_updated_at?->format('Y-m-d H:i:s.u') ?? '9999-12-31 23:59:59.999999';

        return $createdAt.'|'.$lastUpdatedAt;
    }

    private function normalizeSourceOrderPart(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '9:';
        }
        if (ctype_digit($value)) {
            $normalized = ltrim($value, '0');

            return '0:'.str_pad($normalized === '' ? '0' : $normalized, 32, '0', STR_PAD_LEFT);
        }

        return '1:'.mb_strtolower($value);
    }

    private function needsAutomaticNumber(SpjPackage $package, string $documentType): bool
    {
        $documentType = strtoupper(trim($documentType));
        $hasActiveDocument = $package->documents
            ->contains(fn (SpjDocument $document): bool => $document->document_type === $documentType
                && $document->status !== 'CANCELLED'
                && filled($document->document_number));
        if ($hasActiveDocument) {
            return false;
        }

        return match ($documentType) {
            'PESANAN' => ! $package->transaction->goods->pluck('order_number')->filter()->isNotEmpty(),
            'BAP' => ! $package->transaction->goods->pluck('bap_number')->filter()->isNotEmpty(),
            'BAST' => ! $package->transaction->goods->pluck('bast_number')->filter()->isNotEmpty(),
            'SPK' => blank($package->transaction->workOrder?->spk_number),
            'RAB' => blank($package->transaction->workOrder?->rab_number),
            'SURAT_TUGAS_PERJALANAN_DINAS' => $package->transaction->travels
                ->contains(fn ($travel): bool => ($travel->assignment_letter_date || $travel->departure_date) && blank($travel->assignment_letter_number)),
            default => true,
        };
    }
}
