<?php

namespace App\Services;

use App\Models\Transaction;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpjSourceReconciliationService
{
    public const REVIEWED_NO_BUSINESS_CHANGE = 'REVIEWED_NO_BUSINESS_CHANGE';

    public const ACCEPT_SOURCE = 'ACCEPT_SOURCE';

    public const KEEP_OVERLAY = 'KEEP_OVERLAY';

    /**
     * @return array{
     *     needs_attention:bool,
     *     source_status:string,
     *     requires_reconciliation:bool,
     *     events:Collection<int,object>,
     *     latest:?object,
     *     resolutions:Collection<int,object>,
     *     latest_resolution:?object,
     *     action_hint:?string
     * }
     */
    public function forTransaction(Transaction $transaction): array
    {
        $sourceStatus = strtoupper((string) ($transaction->source_status ?: 'ACTIVE'));
        $requiresReconciliation = (bool) $transaction->requires_reconciliation;
        $events = $this->events($transaction->id);
        $latest = $events->first();
        $resolutions = $this->resolutions($transaction->id);

        return [
            'needs_attention' => $sourceStatus === 'SOURCE_MISSING' || $requiresReconciliation,
            'source_status' => $sourceStatus,
            'requires_reconciliation' => $requiresReconciliation,
            'events' => $events,
            'latest' => $latest,
            'resolutions' => $resolutions,
            'latest_resolution' => $resolutions->first(),
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

    /** @return Collection<int,object> */
    public function resolutions(int $transactionId): Collection
    {
        if (! Schema::connection('school')->hasTable('transaction_source_reconciliations')) {
            return collect();
        }

        return DB::connection('school')
            ->table('transaction_source_reconciliations')
            ->where('transaction_id', $transactionId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (object $resolution): object {
                $resolution->label = $this->resolutionLabel((string) $resolution->resolution);

                return $resolution;
            });
    }

    /**
     * @return array{id:int,resolution:string,label:string,resolved_at:mixed}
     */
    public function resolve(
        Transaction $transaction,
        string $resolution,
        ?string $notes,
        ?int $resolvedBy,
        ?int $sourceEventId = null,
    ): array {
        $resolution = strtoupper(trim($resolution));
        if (! in_array($resolution, [self::REVIEWED_NO_BUSINESS_CHANGE, self::ACCEPT_SOURCE, self::KEEP_OVERLAY], true)) {
            throw new DomainException('Keputusan rekonsiliasi tidak valid.');
        }

        return DB::connection('school')->transaction(function () use ($transaction, $resolution, $notes, $resolvedBy, $sourceEventId): array {
            DB::connection('school')->table('transactions')->where('id', $transaction->id)->lockForUpdate()->first();
            $transaction->refresh()->load('spjPackage');
            $report = $this->forTransaction($transaction);

            if ($report['source_status'] === 'SOURCE_MISSING') {
                throw new DomainException('Transaksi masih hilang dari sumber ARKAS/BKU. Sinkronkan atau verifikasi sumber terlebih dahulu.');
            }
            if (! $report['requires_reconciliation']) {
                throw new DomainException('Transaksi ini tidak lagi memerlukan rekonsiliasi.');
            }

            $latest = $report['latest'];
            if ($latest === null) {
                throw new DomainException('Peristiwa perubahan sumber tidak ditemukan. Sinkronkan ulang ARKAS/BKU sebelum menyelesaikan rekonsiliasi.');
            }
            if ($sourceEventId !== null && (int) $latest->id !== $sourceEventId) {
                throw new DomainException('Sumber ARKAS/BKU berubah lagi sejak panel dibuka. Muat ulang halaman dan tinjau perubahan terbaru.');
            }

            $hasBusinessDiff = $latest->changes !== [];
            $packageStatus = strtoupper((string) ($transaction->spjPackage?->status ?: 'DRAFT'));
            $lockedPackage = in_array($packageStatus, ['NUMBERED', 'FINAL'], true);

            if ($resolution === self::REVIEWED_NO_BUSINESS_CHANGE && $hasBusinessDiff) {
                throw new DomainException('Masih ada perubahan nilai sumber yang harus diputuskan. Gunakan keputusan menerima sumber atau mempertahankan overlay SPJ.');
            }
            if (in_array($resolution, [self::ACCEPT_SOURCE, self::KEEP_OVERLAY], true) && ! $hasBusinessDiff) {
                throw new DomainException('Tidak ada perubahan nilai bisnis aktif. Gunakan Tandai Sudah Ditinjau.');
            }
            if ($lockedPackage && $hasBusinessDiff) {
                throw new DomainException('Paket sudah bernomor/final. Perubahan nilai sumber harus ditangani melalui workflow pembatalan, reissue, atau revisi resmi.');
            }

            $now = now();
            $id = DB::connection('school')->table('transaction_source_reconciliations')->insertGetId([
                'transaction_id' => $transaction->id,
                'source_event_id' => $latest->id,
                'resolution' => $resolution,
                'notes' => filled($notes) ? trim((string) $notes) : null,
                'resolved_by' => $resolvedBy,
                'resolved_at' => $now,
                'source_hash' => $transaction->source_hash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::connection('school')->table('transactions')->where('id', $transaction->id)->update([
                'requires_reconciliation' => false,
                'updated_at' => $now,
            ]);

            return [
                'id' => $id,
                'resolution' => $resolution,
                'label' => $this->resolutionLabel($resolution),
                'resolved_at' => $now,
            ];
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

    private function resolutionLabel(string $resolution): string
    {
        return match ($resolution) {
            self::REVIEWED_NO_BUSINESS_CHANGE => 'Sudah ditinjau — tidak ada perubahan nilai bisnis',
            self::ACCEPT_SOURCE => 'Perubahan sumber diterima',
            self::KEEP_OVERLAY => 'Overlay SPJ dipertahankan',
            default => str_replace('_', ' ', $resolution),
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

        if ($latest && $latest->changes === []) {
            return 'Perubahan sumber terdeteksi pada metadata/snapshot, tetapi tidak ada perubahan nilai bisnis aktif. Setelah diperiksa, tandai rekonsiliasi sebagai sudah ditinjau.';
        }

        $packageStatus = strtoupper((string) ($transaction->spjPackage?->status ?: 'DRAFT'));
        if (in_array($packageStatus, ['NUMBERED', 'FINAL'], true)) {
            return 'Dokumen sudah bernomor/final. Jangan mengubahnya diam-diam; tinjau perbedaan lalu gunakan workflow pembatalan/reissue/revisi resmi bila perubahan sumber harus diadopsi.';
        }

        return 'Tinjau perbedaan sumber di bawah. Pilih apakah perubahan sumber diterima atau overlay manual SPJ tetap dipertahankan.';
    }
}
