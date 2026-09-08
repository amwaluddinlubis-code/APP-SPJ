<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpjSourceReconciliationService
{
    /**
     * @return array{
     *     needs_attention:bool,
     *     source_status:string,
     *     requires_reconciliation:bool,
     *     events:Collection<int,object>,
     *     latest:?object,
     *     action_hint:?string
     * }
     */
    public function forTransaction(Transaction $transaction): array
    {
        $sourceStatus = strtoupper((string) ($transaction->source_status ?: 'ACTIVE'));
        $requiresReconciliation = (bool) $transaction->requires_reconciliation;
        $events = $this->events($transaction->id);
        $latest = $events->first();

        return [
            'needs_attention' => $sourceStatus === 'SOURCE_MISSING' || $requiresReconciliation,
            'source_status' => $sourceStatus,
            'requires_reconciliation' => $requiresReconciliation,
            'events' => $events,
            'latest' => $latest,
            'action_hint' => $this->actionHint($transaction, $sourceStatus, $requiresReconciliation, $latest),
        ];
    }

    /** @return Collection<int,object> */
    public function events(int $transactionId): Collection
    {
        if (! Schema::connection('school')->hasTable('transaction_source_events')) {
            return collect();
        }

        return DB::connection('school')
            ->table('transaction_source_events')
            ->where('transaction_id', $transactionId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (object $event): object {
                $before = $this->decodeSnapshot($event->before_snapshot ?? null);
                $after = $this->decodeSnapshot($event->after_snapshot ?? null);

                $event->before = $before;
                $event->after = $after;
                $event->changes = $this->diff($before, $after);
                $event->label = $this->eventLabel((string) $event->event_type);

                return $event;
            });
    }

    /**
     * @param  array<string,mixed>  $before
     * @param  array<string,mixed>  $after
     * @return array<int,array{field:string,label:string,before:mixed,after:mixed}>
     */
    public function diff(array $before, array $after): array
    {
        $fields = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        $changes = [];

        foreach ($fields as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if ($this->comparable($old) === $this->comparable($new)) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $this->fieldLabel($field),
                'before' => $old,
                'after' => $new,
            ];
        }

        return $changes;
    }

    /** @return array<string,mixed> */
    private function decodeSnapshot(mixed $snapshot): array
    {
        if (is_array($snapshot)) {
            return $snapshot;
        }
        if (! is_string($snapshot) || trim($snapshot) === '') {
            return [];
        }

        $decoded = json_decode($snapshot, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function comparable(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }

    private function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            'SOURCE_CHANGED' => 'Data sumber berubah',
            'SOURCE_ITEM_CHANGED' => 'Rincian sumber berubah',
            'SOURCE_MISSING' => 'Transaksi hilang dari sumber',
            'SOURCE_RETURNED' => 'Transaksi kembali dari sumber',
            default => str_replace('_', ' ', $eventType),
        };
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'transaction_date' => 'Tanggal transaksi',
            'description' => 'Uraian sumber',
            'activity_code' => 'Kode kegiatan',
            'activity_name' => 'Nama kegiatan',
            'account_code' => 'Kode rekening',
            'account_name' => 'Nama rekening',
            'recipient_name' => 'Penerima BKU',
            'gross_amount' => 'Bruto',
            'ppn' => 'PPN',
            'pph21' => 'PPh 21',
            'pph22' => 'PPh 22',
            'pph23' => 'PPh 23',
            'pph4' => 'PPh 4(2)',
            'sspd' => 'SSPD/Pajak Daerah',
            'tax_total' => 'Total pajak',
            'net_amount' => 'Nilai dibayarkan',
            'is_siplah' => 'SiPLah',
            'source_item_id' => 'ID rincian sumber',
            'quantity' => 'Volume',
            'unit' => 'Satuan',
            'unit_price' => 'Harga satuan',
            'amount' => 'Jumlah rincian',
            'source_status' => 'Status sumber',
            'source_missing_since' => 'Mulai hilang sejak',
            default => str_replace('_', ' ', ucfirst($field)),
        };
    }

    private function actionHint(Transaction $transaction, string $sourceStatus, bool $requiresReconciliation, ?object $latest): ?string
    {
        if ($sourceStatus === 'SOURCE_MISSING') {
            return 'Pertahankan pekerjaan SPJ yang ada. Periksa kembali data ARKAS sebelum melakukan pembatalan atau revisi melalui workflow resmi.';
        }

        if (! $requiresReconciliation) {
            return $latest?->event_type === 'SOURCE_RETURNED'
                ? 'Sumber sudah kembali dan tidak ada perubahan aktif yang memerlukan rekonsiliasi.'
                : null;
        }

        $packageStatus = strtoupper((string) ($transaction->spjPackage?->status ?: 'DRAFT'));
        if (in_array($packageStatus, ['NUMBERED', 'FINAL'], true)) {
            return 'Dokumen sudah bernomor/final. Jangan mengubahnya diam-diam; tinjau perbedaan lalu gunakan workflow pembatalan/reissue/revisi resmi bila perubahan sumber harus diadopsi.';
        }

        return 'Tinjau perbedaan sumber di bawah. Overlay manual tetap dipertahankan; sesuaikan Paket SPJ hanya bila perubahan ARKAS memang harus diadopsi.';
    }
}
