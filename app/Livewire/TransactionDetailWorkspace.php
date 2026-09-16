<?php

namespace App\Livewire;

use App\Models\Transaction;
use App\Services\SpjDescriptionService;
use App\Services\SpjSourceReconciliationService;
use App\Support\ActiveSpjContext;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class TransactionDetailWorkspace extends Component
{
    public int $transactionId;

    public string $paymentDescription = '';

    /** @var array<int, string> */
    public array $itemDescriptions = [];

    public string $resolution = '';

    public string $resolutionNotes = '';

    public ?int $sourceEventId = null;

    public function mount(int $transactionId, ActiveSpjContext $context): void
    {
        $transaction = Transaction::query()->find($transactionId);
        if (! $transaction || ! $context->matchesTransaction($transaction)) {
            $this->redirectRoute('transactions.index');

            return;
        }

        $this->transactionId = $transactionId;
        $this->loadTransaction();
    }

    public function saveDescriptions(SpjDescriptionService $descriptions, ActiveSpjContext $context): void
    {
        $transaction = $this->transaction();
        if (! $context->matchesTransaction($transaction)) {
            $this->redirectRoute('transactions.index');

            return;
        }
        if ($transaction->spjPackage?->status === 'FINAL') {
            $this->addError('form', 'Uraian SPJ tidak dapat diubah karena paket sudah FINAL.');
            $this->dispatch('app-notify', type: 'error', message: 'Uraian SPJ tidak dapat diubah karena paket sudah FINAL.');

            return;
        }

        try {
            $data = $this->validate([
                'paymentDescription' => ['nullable', 'string', 'max:4000'],
                'itemDescriptions' => ['array'],
                'itemDescriptions.*' => ['required', 'string', 'max:4000'],
            ]);
        } catch (ValidationException $exception) {
            $this->dispatch('app-notify', type: 'error', message: $exception->validator->errors()->first());

            throw $exception;
        }
        $itemIds = $transaction->items->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach (array_keys($data['itemDescriptions'] ?? []) as $itemId) {
            if (! in_array((int) $itemId, $itemIds, true)) {
                abort(422, 'Rincian transaksi tidak valid.');
            }
        }

        if (array_key_exists('paymentDescription', $data)) {
            $descriptions->updatePaymentDescription($transaction, $data['paymentDescription'] ?? null);
        }
        foreach ($data['itemDescriptions'] ?? [] as $itemId => $description) {
            $transaction->items->firstWhere('id', (int) $itemId)->update([
                'item_description' => trim($description),
            ]);
        }

        $this->loadTransaction();
        session()->flash('success', 'Uraian SPJ berhasil disimpan tanpa mengubah data sumber ARKAS/BKU atau penomoran.');
        $this->dispatch('app-notify', type: 'success', message: 'Koreksi uraian berhasil disimpan.');
    }

    public function resolveReconciliation(?string $requestedResolution, SpjSourceReconciliationService $service, ActiveSpjContext $context): void
    {
        if ($requestedResolution !== null) {
            $this->resolution = $requestedResolution;
        }

        $transaction = $this->transaction();
        if (! $context->matchesTransaction($transaction)) {
            $this->redirectRoute('transactions.index');

            return;
        }

        $data = $this->validate([
            'resolution' => ['required', 'string', Rule::in([
                SpjSourceReconciliationService::REVIEWED_NO_BUSINESS_CHANGE,
                SpjSourceReconciliationService::ACCEPT_SOURCE,
                SpjSourceReconciliationService::KEEP_OVERLAY,
            ])],
            'sourceEventId' => ['nullable', 'integer'],
            'resolutionNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $service->resolve($transaction, $data['resolution'], $data['resolutionNotes'] ?? null, auth()->id() ? (int) auth()->id() : null, $data['sourceEventId'] ?? null);
        } catch (DomainException $exception) {
            $this->addError('resolution', $exception->getMessage());

            return;
        }

        $this->loadTransaction();
        $this->resolution = '';
        $this->resolutionNotes = '';
        session()->flash('success', 'Rekonsiliasi selesai: '.$result['label'].'.');
    }

    public function render(): View
    {
        $transaction = $this->transaction();
        $rupiah = fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.');

        return view('livewire.transaction-detail-workspace', [
            'transaction' => $transaction,
            'headerVisual' => $this->headerVisual($transaction),
            'paymentMethod' => $this->normalizePaymentMethod($transaction->payment_method, $transaction),
            'previousTransaction' => $this->adjacentTransaction($transaction, false),
            'nextTransaction' => $this->adjacentTransaction($transaction, true),
            'rupiah' => $rupiah,
            'totalItems' => $transaction->items->sum('amount'),
            'descriptionsFilled' => $transaction->items->filter(fn ($item): bool => filled($item->item_description))->count(),
            'descriptionsComplete' => $transaction->items->isNotEmpty() && $transaction->items->every(fn ($item): bool => filled($item->item_description)),
            'spjTypeLabel' => fn ($value): string => match (strtoupper((string) $value)) {
                'JASA_LAINNYA' => 'Jasa Lainnya',
                'SPPD' => 'SPPD',
                'HONOR_PEGAWAI' => 'Honor Pegawai',
                'BARANG' => 'Barang',
                'KONSUMSI' => 'Konsumsi',
                'PEMELIHARAAN' => 'Pemeliharaan',
                default => ucwords(strtolower(str_replace('_', ' ', (string) $value))),
            },
            'sourceStatus' => strtoupper((string) ($transaction->source_status ?: 'ACTIVE')),
            'needsAttention' => strtoupper((string) ($transaction->source_status ?: 'ACTIVE')) === 'SOURCE_MISSING' || (bool) $transaction->requires_reconciliation,
        ]);
    }

    private function transaction(): Transaction
    {
        return Transaction::query()->with([
            'items' => fn ($query) => $query->orderBy('id'), 'goods', 'workers', 'participants', 'travels', 'honors', 'workOrder', 'spjPackage',
        ])->findOrFail($this->transactionId);
    }

    private function loadTransaction(): void
    {
        $transaction = $this->transaction();
        $siplahDescription = $transaction->is_siplah
            ? app(SpjDescriptionService::class)->siplahPaymentDescription($transaction)
            : null;
        $this->paymentDescription = (string) ($transaction->payment_description ?: $siplahDescription ?: $transaction->description ?: '');
        $this->itemDescriptions = $transaction->items->mapWithKeys(fn ($item): array => [(int) $item->id => (string) ($item->item_description ?: $item->description ?: '')])->all();
        $report = app(SpjSourceReconciliationService::class)->forTransaction($transaction);
        $this->sourceEventId = $report['latest']?->id ? (int) $report['latest']->id : null;
    }

    private function adjacentTransaction(Transaction $transaction, bool $next): ?Transaction
    {
        $query = Transaction::query()->activeContext();
        $date = $transaction->transaction_date;
        if ($next) {
            return $query->where(function ($query) use ($transaction, $date): void {
                $query->where('transaction_date', '>', $date)->orWhere(function ($query) use ($transaction, $date): void {
                    $query->where('transaction_date', $date)->where('id', '>', $transaction->id);
                });
            })->orderBy('transaction_date')->orderBy('id')->first();
        }

        return $query->where(function ($query) use ($transaction, $date): void {
            $query->whereNull('transaction_date')->orWhere('transaction_date', '<', $date)->orWhere(function ($query) use ($transaction, $date): void {
                $query->where('transaction_date', $date)->where('id', '<', $transaction->id);
            });
        })->orderByDesc('transaction_date')->orderByDesc('id')->first();
    }

    private function headerVisual(Transaction $transaction): array
    {
        $haystack = mb_strtolower(implode(' ', [$transaction->spj_category, $transaction->account_code, $transaction->account_name, $transaction->description, $transaction->activity_name]));
        if ($transaction->spj_category === 'HONOR_PEGAWAI' || str_contains($haystack, 'honor')) {
            return ['label' => 'Honor Pegawai', 'image' => null];
        }
        if (str_contains($haystack, 'buku')) {
            return ['label' => 'Belanja Buku', 'image' => 'images/spj-categories/belanja-buku.png'];
        }
        if (str_starts_with((string) $transaction->account_code, '5.2')) {
            return ['label' => 'Belanja Modal Peralatan dan Mesin', 'image' => 'images/spj-categories/belanja-modal.png'];
        }
        if (str_contains($haystack, 'makanan') || str_contains($haystack, 'minuman') || str_contains($haystack, 'konsumsi')) {
            return ['label' => 'Belanja Konsumsi', 'image' => 'images/spj-categories/belanja-konsumsi.png'];
        }
        if (str_starts_with((string) $transaction->account_code, '5.1.02.04') || str_contains($haystack, 'perjalanan')) {
            return ['label' => 'Perjalanan Dinas', 'image' => 'images/spj-categories/perjalanan-dinas.png'];
        }
        if (str_starts_with((string) $transaction->account_code, '5.1.02.03') || str_contains($haystack, 'pemeliharaan')) {
            return ['label' => 'Jasa Pemeliharaan', 'image' => 'images/spj-categories/jasa-pemeliharaan.png'];
        }

        return ['label' => 'Barang Habis Pakai / ATK / Peralatan Olahraga dll', 'image' => 'images/spj-categories/barang-habis-pakai.png'];
    }

    private function normalizePaymentMethod(?string $value, Transaction $transaction): string
    {
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['transfer_bank', 'siplah', 'tunai'], true)) {
            return $value;
        }
        if ($transaction->is_siplah) {
            return 'siplah';
        }
        $proofNumber = strtolower((string) $transaction->no_bukti);
        if (str_contains($proofNumber, 'non_tunai') || str_contains($proofNumber, 'non tunai') || str_starts_with($proofNumber, 'bnu')) {
            return 'transfer_bank';
        }
        if (str_contains($value, 'transfer') || str_contains($value, 'cms') || str_contains($value, 'non tunai')) {
            return 'transfer_bank';
        }

        return 'tunai';
    }
}
