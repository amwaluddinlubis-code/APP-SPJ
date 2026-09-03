<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TransactionItem extends Model
{
    protected $connection = 'school';

    protected $fillable = ['transaction_id', 'source_item_id', 'description', 'item_description', 'quantity', 'unit', 'unit_price', 'amount', 'source_status', 'last_seen_sync_run_id', 'source_missing_since'];

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
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2', 'source_missing_since' => 'datetime'];
    }
}
