<?php

namespace App\Models;

use App\Support\ActiveSpjContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SpjFreshTransaction extends Model
{
    protected $table = 'spj_fresh_transactions';

    protected $connection = 'school';

    protected $fillable = [
        'fiscal_year_id', 'fund_source_id', 'source_id', 'source_table', 'source_key',
        'raw_mirror_row_id', 'spj_category', 'payment_description', 'payment_method',
        'payment_reference', 'receipt_recipient_name', 'source_status',
        'requires_reconciliation', 'source_missing_since',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SpjFreshTransactionItem::class);
    }

    public function package(): HasOne
    {
        return $this->hasOne(SpjFreshPackage::class);
    }

    public function spjPackage(): HasOne
    {
        return $this->package();
    }

    public function getItemsCountAttribute(): int
    {
        return $this->relationLoaded('items')
            ? $this->items->count()
            : (int) $this->items()->count();
    }

    public function scopeForSpjContext(Builder $query, ActiveSpjContext $context): Builder
    {
        return $query
            ->where('fiscal_year_id', $context->fiscalYearId())
            ->where('fund_source_id', $context->fundSourceId());
    }

    public function getRouteIdentifierAttribute(): string
    {
        return (string) ($this->source_key ?: $this->id);
    }

    public function rawMirrorRow(): BelongsTo
    {
        return $this->belongsTo(ArkasRawMirrorRow::class, 'raw_mirror_row_id');
    }

    protected function casts(): array
    {
        return [
            'requires_reconciliation' => 'boolean',
            'source_missing_since' => 'datetime',
        ];
    }

    public function getNoBuktiAttribute(): ?string
    {
        return $this->sourceValue(['no_bukti', 'nomor_bukti']);
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->sourceValue(['uraian', 'description']);
    }

    public function getAccountCodeAttribute(): ?string
    {
        return $this->sourceValue(['kode_rekening', 'account_code']);
    }

    public function getActivityCodeAttribute(): ?string
    {
        $activityCode = $this->sourceValue(['kode_kegiatan', 'activity_code'])
            ?: $this->itemSourceValue(['kode_kegiatan', 'activity_code']);
        if (filled($activityCode)) {
            return $activityCode;
        }

        return $this->rkasValue('activity_code');
    }

    public function getActivityNameAttribute(): ?string
    {
        $activityName = $this->sourceValue(['nama_kegiatan', 'activity_name'])
            ?: $this->itemSourceValue(['nama_kegiatan', 'activity_name']);
        if (filled($activityName)) {
            return $activityName;
        }

        return $this->rkasValue('activity_name');
    }

    public function getRecipientNameAttribute(): ?string
    {
        $recipient = $this->sourceValue(['nama_penerima', 'penerima', 'recipient_name']);
        if (filled($recipient)) {
            return $recipient;
        }

        $notaSourceKey = $this->sourceValue(['id_kas_nota']);
        if (blank($notaSourceKey)) {
            return null;
        }

        $recipient = DB::connection('school')
            ->table('arkas_raw_mirror_rows as nota_raw')
            ->join('arkas_raw_mirror_tables as nota_table', 'nota_table.id', '=', 'nota_raw.mirror_table_id')
            ->where('nota_table.source_id', $this->source_id)
            ->where('nota_table.source_table', 'kas_umum_nota')
            ->where('nota_table.status', 'ACTIVE')
            ->whereRaw("json_extract(nota_raw.payload, '$.id_kas_nota') = ?", [$notaSourceKey])
            ->value(DB::raw("json_extract(nota_raw.payload, '$.nama_toko')"));

        return filled($recipient) ? (string) $recipient : null;
    }

    public function getEffectiveReceiptRecipientNameAttribute(): ?string
    {
        return $this->receipt_recipient_name ?: $this->recipient_name;
    }

    public function getIsSiplahAttribute(): bool
    {
        $notaSourceKeys = $this->sourceItems()
            ->map(function (SpjFreshTransactionItem $item): ?string {
                $payload = $item->rawMirrorRow?->payload ?? [];
                $sourceKey = trim((string) ($payload['id_kas_nota'] ?? ''));

                return $sourceKey === '' ? null : $sourceKey;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
        if ($notaSourceKeys === []) {
            return false;
        }

        return DB::connection('school')
            ->table('arkas_raw_mirror_rows as nota_raw')
            ->join('arkas_raw_mirror_tables as nota_table', 'nota_table.id', '=', 'nota_raw.mirror_table_id')
            ->where('nota_table.source_id', $this->source_id)
            ->where('nota_table.source_table', 'kas_umum_nota')
            ->where('nota_table.status', 'ACTIVE')
            ->whereRaw("json_extract(nota_raw.payload, '$.id_kas_nota') IN (".implode(',', array_fill(0, count($notaSourceKeys), '?')).')', $notaSourceKeys)
            ->whereRaw("CAST(COALESCE(json_extract(nota_raw.payload, '$.is_beli_di_siplah'), 0) AS INTEGER) = 1")
            ->exists();
    }

    private function rkasValue(string $column): ?string
    {
        $periodSourceKey = $this->sourceValue(['id_rapbs_periode'])
            ?: $this->itemSourceValue(['id_rapbs_periode']);
        if (blank($periodSourceKey)) {
            return null;
        }

        $rkasSourceKey = DB::connection('school')
            ->table('arkas_rkas_periods')
            ->where('source_rapbs_period_id', $periodSourceKey)
            ->where('fiscal_year_id', $this->fiscal_year_id)
            ->where('fund_source_id', $this->fund_source_id)
            ->value('source_rapbs_id');

        if (blank($rkasSourceKey)) {
            return null;
        }

        $value = DB::connection('school')
            ->table('arkas_rkas_items')
            ->where('fiscal_year_id', $this->fiscal_year_id)
            ->where('fund_source_id', $this->fund_source_id)
            ->where('source_rapbs_id', $rkasSourceKey)
            ->value($column);

        return filled($value) ? (string) $value : null;
    }

    public function getTransactionDateAttribute(): ?Carbon
    {
        $value = $this->sourceValue(['tanggal_transaksi', 'tanggal']);

        return filled($value) ? Carbon::parse($value) : null;
    }

    public function getGrossAmountAttribute(): float
    {
        $items = $this->sourceItems();
        if ($items->isEmpty()) {
            return (float) ($this->sourceValue(['saldo', 'jumlah', 'nilai', 'nominal']) ?: 0);
        }

        return (float) $items->sum(function (SpjFreshTransactionItem $item): float {
            $payload = $item->rawMirrorRow?->payload ?? [];

            return (float) ($payload['saldo'] ?? $payload['jumlah'] ?? $payload['nilai'] ?? $payload['nominal'] ?? 0);
        });
    }

    public function getTaxTotalAttribute(): float
    {
        return array_sum($this->tax_breakdown);
    }

    /** @return array{ppn:float,pph21:float,pph22:float,pph23:float,pph4:float,sspd:float} */
    public function getTaxBreakdownAttribute(): array
    {
        $breakdown = [
            'ppn' => 0.0,
            'pph21' => 0.0,
            'pph22' => 0.0,
            'pph23' => 0.0,
            'pph4' => 0.0,
            'sspd' => 0.0,
        ];
        $parentIds = $this->sourceItemIds();
        if ($parentIds === [] || blank($this->source_id)) {
            return $breakdown;
        }

        $rows = DB::connection('school')
            ->table('arkas_raw_mirror_rows as tax_raw')
            ->join('arkas_raw_mirror_tables as tax_table', 'tax_table.id', '=', 'tax_raw.mirror_table_id')
            ->where('tax_table.source_id', $this->source_id)
            ->where('tax_table.source_table', 'kas_umum')
            ->where('tax_table.status', 'ACTIVE')
            ->whereRaw("COALESCE(json_extract(tax_raw.payload, '$.soft_delete'), '0') != '1'")
            ->whereRaw("CAST(COALESCE(json_extract(tax_raw.payload, '$.id_ref_bku'), 0) AS INTEGER) IN (10, 30)")
            ->whereRaw("json_extract(tax_raw.payload, '$.parent_id_kas_umum') IN (".implode(',', array_fill(0, count($parentIds), '?')).')', $parentIds)
            ->get(['tax_raw.payload']);

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)) {
                continue;
            }

            $field = $this->taxField($payload);
            if ($field === null) {
                continue;
            }

            $breakdown[$field] += (float) ($payload['saldo'] ?? $payload['jumlah'] ?? $payload['nilai'] ?? $payload['nominal'] ?? 0);
        }

        return $breakdown;
    }

    public function getNetAmountAttribute(): float
    {
        return $this->gross_amount - $this->tax_total;
    }

    /** @return array<int, string> */
    private function sourceItemIds(): array
    {
        return $this->sourceItems()
            ->map(function (SpjFreshTransactionItem $item): string {
                $payload = $item->rawMirrorRow?->payload ?? [];

                return trim((string) ($payload['id_kas_umum'] ?? $item->source_key));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $payload */
    private function taxField(array $payload): ?string
    {
        foreach ([
            'ppn' => ['is_ppn'],
            'pph21' => ['is_pph_21', 'is_pph21'],
            'pph22' => ['is_pph_22', 'is_pph22'],
            'pph23' => ['is_pph_23', 'is_pph23'],
            'pph4' => ['is_pph_4', 'is_pph4'],
            'sspd' => ['is_sspd'],
        ] as $field => $keys) {
            foreach ($keys as $key) {
                if ((int) ($payload[$key] ?? 0) === 1) {
                    return $field;
                }
            }
        }

        return null;
    }

    /** @return EloquentCollection<int, SpjFreshTransactionItem> */
    private function sourceItems(): EloquentCollection
    {
        if ($this->relationLoaded('items')) {
            /** @var EloquentCollection<int, SpjFreshTransactionItem> $items */
            $items = $this->getRelation('items');
            $items->loadMissing('rawMirrorRow');

            return $items;
        }

        /** @var EloquentCollection<int, SpjFreshTransactionItem> $items */
        $items = $this->items()
            ->with('rawMirrorRow')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $items;
    }

    /** @param array<int, string> $keys */
    private function sourceValue(array $keys): ?string
    {
        $payload = $this->rawMirrorRow?->payload ?? [];
        foreach ($keys as $key) {
            if (filled($payload[$key] ?? null)) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    /** @param array<int, string> $keys */
    private function itemSourceValue(array $keys): ?string
    {
        foreach ($this->sourceItems() as $item) {
            $payload = $item->rawMirrorRow?->payload ?? [];
            foreach ($keys as $key) {
                if (filled($payload[$key] ?? null)) {
                    return (string) $payload[$key];
                }
            }
        }

        return null;
    }
}
