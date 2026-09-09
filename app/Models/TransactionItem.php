<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TransactionItem extends Model
{
    protected $connection = 'school';

    protected $fillable = ['transaction_id', 'source_item_id', 'siplah_item_mpid', 'rkas_item_code', 'rkas_item_name', 'siplah_item_name', 'siplah_mapped_quantity', 'siplah_quantity_received', 'siplah_unit_dpp', 'siplah_unit_ppn', 'siplah_unit_price', 'siplah_unit_insurance_cost', 'siplah_unit_packaging_cost', 'siplah_item_metadata', 'description', 'item_description', 'quantity', 'unit', 'unit_price', 'amount', 'source_status', 'last_seen_sync_run_id', 'source_missing_since'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function goods(): HasOne
    {
        return $this->hasOne(SpjGoods::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SpjParticipant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function honors(): HasMany
    {
        return $this->hasMany(SpjHonor::class)->orderBy('sort_order')->orderBy('id');
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2', 'siplah_mapped_quantity' => 'decimal:2', 'siplah_quantity_received' => 'decimal:2', 'siplah_unit_dpp' => 'decimal:2', 'siplah_unit_ppn' => 'decimal:2', 'siplah_unit_price' => 'decimal:2', 'siplah_unit_insurance_cost' => 'decimal:2', 'siplah_unit_packaging_cost' => 'decimal:2', 'siplah_item_metadata' => 'array', 'source_missing_since' => 'datetime'];
    }
}
