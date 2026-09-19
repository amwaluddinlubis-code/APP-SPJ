<?php

namespace App\Livewire;

use App\Models\SpjFreshTransaction;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\OperationalAuditService;
use App\Services\SpjDescriptionService;
use App\Services\SpjSourceReconciliationService;
use App\Services\SpjV2MutationContextService;
use App\Support\ActiveSpjContext;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class TransactionDetailWorkspace extends Component
{
    public string $transactionId;

    public string $paymentDescription = '';

    /** @var array<int, string> */
    public array $itemDescriptions = [];

    public string $resolution = '';

    public string $resolutionNotes = '';

    public ?int $sourceEventId = null;

    public function mount(string $transactionId, ActiveSpjContext $context): void
    {
        $transaction = $this->transaction($context, $transactionId);
        if ($transaction === null) {
            $this->redirectRoute('transactions.index');

            return;
        }

        $this->transactionId = $transaction->source_key ?: $transactionId;
        $this->loadTransaction();
    }

    public function saveDescriptions(SpjDescriptionService $descriptions, OperationalAuditService $audit, ActiveSpjContext $context): void
    {
        $this->authorizeOperatorOrAdministrator();
        $transaction = $this->transaction($context);
        if ($transaction === null || ! app(SpjV2MutationContextService::class)->authorizeTransactionDescription($transaction)) {
            $this->redirectRoute('transactions.index');

            return;
        }
        if ($transaction->spjPackage?->status === 'FINAL') {
            $this->addError('form', 'Uraian SPJ tidak dapat diubah karena paket sudah FINAL.');
            $this->dispatch('app-notify', type: 'error', message: 'Uraian SPJ tidak dapat diubah karena paket sudah FINAL.');

            return;
        }
        if (! $transaction->exists) {
            $this->addError('form', 'Transaksi fresh ARKAS belum memiliki overlay SPJ yang dapat diedit.');

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

        $audit->record(
            $context->fiscalYearId(),
            'TRANSACTION',
            $transaction->id,
            'DESCRIPTION_UPDATED',
            'Uraian pembayaran/item diperbarui pada fund source '.$context->fundSourceId().'.',
        );

        $this->loadTransaction();
        session()->flash('success', 'Uraian SPJ berhasil disimpan tanpa mengubah data sumber ARKAS/BKU atau penomoran.');
        $this->dispatch('app-notify', type: 'success', message: 'Koreksi uraian berhasil disimpan.');
    }

    public function resolveReconciliation(?string $requestedResolution, SpjSourceReconciliationService $service, OperationalAuditService $audit, ActiveSpjContext $context): void
    {
        $this->authorizeOperatorOrAdministrator();
        if ($requestedResolution !== null) {
            $this->resolution = $requestedResolution;
        }

        $transaction = $this->transaction($context);
        if ($transaction === null || ! $transaction->exists || ! $context->matchesTransaction($transaction)) {
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

        $audit->record(
            (int) $transaction->fiscal_year_id,
            'TRANSACTION',
            $transaction->id,
            'SOURCE_RECONCILIATION_RESOLVED',
            'Rekonsiliasi sumber '.$result['resolution'].' diselesaikan pada fund source '.$context->fundSourceId().'.',
        );

        $this->loadTransaction();
        $this->resolution = '';
        $this->resolutionNotes = '';
        session()->flash('success', 'Rekonsiliasi selesai: '.$result['label'].'.');
    }

    public function render(): View
    {
        $transaction = $this->transaction(app(ActiveSpjContext::class));
        abort_unless($transaction !== null, 404);
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

    private function transaction(ActiveSpjContext $context, ?string $sourceIdentifier = null): ?Transaction
    {
        $sourceIdentifier ??= $this->transactionId;
        $transaction = Transaction::query()->with([
            'items' => fn ($query) => $query->orderBy('id'), 'goods', 'workers', 'participants', 'travels', 'honors', 'workOrder', 'spjPackage',
        ])->forSpjContext($context)->forSourceIdentifier($sourceIdentifier)->first();
        if ($transaction === null) {
            $transaction = app(SpjV2MutationContextService::class)->resolveTransactionForDescription($sourceIdentifier);
            if ($transaction !== null) {
                return Transaction::query()->with([
                    'items' => fn ($query) => $query->orderBy('id'), 'goods', 'workers', 'participants', 'travels', 'honors', 'workOrder', 'spjPackage',
                ])->find($transaction->id);
            }

            return $this->transactionFromFresh($sourceIdentifier, $context);
        }
        $fresh = SpjFreshTransaction::query()
            ->with(['rawMirrorRow', 'items.rawMirrorRow'])
            ->forSpjContext($context)
            ->where('source_table', 'kas_umum')
            ->where('source_key', $transaction->source_key ?: $sourceIdentifier)
            ->first();

        if ($fresh !== null) {
            $payload = $fresh->rawMirrorRow?->payload ?? [];
            $gross = (float) $fresh->gross_amount;
            $tax = (float) $fresh->tax_total;
            $transaction->forceFill([
                'no_bukti' => $fresh->no_bukti,
                'transaction_date' => $fresh->transaction_date?->toDateString(),
                'description' => $fresh->description,
                'account_code' => $fresh->account_code,
                'activity_code' => $fresh->activity_code,
                'recipient_name' => $fresh->recipient_name,
                'gross_amount' => $gross,
                'tax_total' => $tax,
                'net_amount' => $gross - $tax,
                'source_status' => $fresh->source_status,
                'requires_reconciliation' => $fresh->requires_reconciliation,
                'source_key' => $fresh->source_key,
                'id_kas_umum' => $payload['id_kas_umum'] ?? $transaction->id_kas_umum,
            ]);
        }

        return $transaction;
    }

    private function transactionFromFresh(string $sourceKey, ActiveSpjContext $context): ?Transaction
    {
        $fresh = SpjFreshTransaction::query()
            ->with(['rawMirrorRow', 'items.rawMirrorRow'])
            ->forSpjContext($context)
            ->where('source_table', 'kas_umum')
            ->where('source_key', $sourceKey)
            ->first();
        if ($fresh === null) {
            return null;
        }

        $payload = $fresh->rawMirrorRow?->payload ?? [];
        $transaction = new Transaction;
        $transaction->forceFill([
            'id' => $fresh->id,
            'fiscal_year_id' => $fresh->fiscal_year_id,
            'fund_source_id' => $fresh->fund_source_id,
            'source_key' => $fresh->source_key,
            'id_kas_umum' => $payload['id_kas_umum'] ?? $fresh->source_key,
            'no_bukti' => $fresh->no_bukti,
            'transaction_date' => $fresh->transaction_date?->toDateString(),
            'description' => $fresh->description,
            'account_code' => $fresh->account_code,
            'activity_code' => $fresh->activity_code,
            'recipient_name' => $fresh->recipient_name,
            'gross_amount' => $fresh->gross_amount,
            'tax_total' => $fresh->tax_total,
            'source_status' => $fresh->source_status,
            'requires_reconciliation' => $fresh->requires_reconciliation,
        ]);
        $transaction->net_amount = (float) $transaction->gross_amount - (float) $transaction->tax_total;
        $items = $fresh->items->map(function ($freshItem) use ($fresh): TransactionItem {
            $itemPayload = $freshItem->rawMirrorRow?->payload ?? [];
            $periodPayload = $this->freshPeriodPayload($itemPayload['id_rapbs_periode'] ?? null, $fresh->source_id);
            $item = new TransactionItem;
            $item->forceFill([
                'id' => $freshItem->id,
                'transaction_id' => $fresh->id,
                'description' => $itemPayload['uraian'] ?? '',
                'item_description' => $freshItem->item_description,
                'quantity' => $periodPayload['volume'] ?? $itemPayload['volume'] ?? 1,
                'unit' => $periodPayload['satuan'] ?? $itemPayload['satuan'] ?? null,
                'unit_price' => $periodPayload['harga_satuan'] ?? null,
                'amount' => $periodPayload['jumlah'] ?? $itemPayload['saldo'] ?? 0,
            ]);

            return $item;
        });
        $transaction->setRelation('items', $items);
        foreach (['goods', 'workers', 'participants', 'travels', 'honors', 'workOrder', 'spjPackage', 'payments'] as $relation) {
            $transaction->setRelation($relation, in_array($relation, ['workOrder', 'spjPackage'], true) ? null : collect());
        }

        return $transaction;
    }

    /** @return array<string, mixed> */
    private function freshPeriodPayload(?string $periodId, ?int $sourceId = null): array
    {
        if (blank($periodId)) {
            return [];
        }

        $payload = DB::connection('school')->table('arkas_raw_mirror_rows as period_raw')
            ->join('arkas_raw_mirror_tables as period_table', 'period_table.id', '=', 'period_raw.mirror_table_id')
            ->when($sourceId !== null, fn ($query) => $query->where('period_table.source_id', $sourceId))
            ->where('period_table.source_table', 'rapbs_periode')
            ->where('period_table.status', 'ACTIVE')
            ->whereRaw("json_extract(period_raw.payload, '$.id_rapbs_periode') = ?", [$periodId])
            ->value('period_raw.payload');

        $decoded = is_string($payload) ? json_decode($payload, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    private function loadTransaction(): void
    {
        $transaction = $this->transaction(app(ActiveSpjContext::class));
        abort_unless($transaction !== null, 404);
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
        if ($date === null) {
            return $query->whereNull('transaction_date')
                ->when($next, fn ($query) => $query->where('id', '>', $transaction->id)->orderBy('id'))
                ->when(! $next, fn ($query) => $query->where('id', '<', $transaction->id)->orderByDesc('id'))
                ->first();
        }
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

    private function authorizeOperatorOrAdministrator(): void
    {
        abort_unless(auth()->user()?->isOperatorOrAdministrator(), 403);
    }
}
