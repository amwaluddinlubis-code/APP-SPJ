<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjFreshTransactionItem extends Model
{
    protected $table = 'spj_fresh_transaction_items';

    protected $connection = 'school';

    protected $fillable = ['spj_fresh_transaction_id', 'source_table', 'source_key', 'raw_mirror_row_id', 'item_description', 'sort_order'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SpjFreshTransaction::class, 'spj_fresh_transaction_id');
    }

    public function rawMirrorRow(): BelongsTo
    {
        return $this->belongsTo(ArkasRawMirrorRow::class, 'raw_mirror_row_id');
    }

    public function getQuantityAttribute(): float
    {
        return max(1, (float) ($this->sourceValue(['volume', 'quantity']) ?? 1));
    }

    public function getUnitAttribute(): ?string
    {
        $value = $this->sourceValue(['satuan', 'satuan_barang', 'unit']);

        return $value === null ? null : trim((string) $value);
    }

    public function getUnitPriceAttribute(): float
    {
        $amount = $this->amount;
        $quantity = $this->quantity;

        return (float) ($this->sourceValue(['harga_satuan', 'unit_price']) ?? ($quantity > 0 ? $amount / $quantity : 0));
    }

    public function getAmountAttribute(): float
    {
        return (float) ($this->sourceValue(['saldo', 'jumlah', 'nilai', 'nominal']) ?? 0);
    }

    public function getAccountCodeAttribute(): ?string
    {
        $value = $this->sourceValue(['kode_rekening', 'account_code']);

        return $value === null ? null : trim((string) $value);
    }

    public function getAccountNameAttribute(): ?string
    {
        $value = $this->sourceValue(['nama_rekening', 'account_name']);

        return $value === null ? null : trim((string) $value);
    }

    /** @param array<int, string> $keys */
    private function sourceValue(array $keys): mixed
    {
        $payload = $this->rawMirrorRow?->payload ?? [];
        $normalized = [];
        foreach ($payload as $key => $value) {
            $normalized[strtolower((string) $key)] = $value;
        }

        foreach ($keys as $key) {
            $value = $normalized[strtolower($key)] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
