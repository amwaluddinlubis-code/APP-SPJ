<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $connection = 'school';

    protected $fillable = ['transaction_id', 'scope_key', 'receipt_sequence', 'receipt_date', 'status', 'notes', 'is_late_entry'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    protected function casts(): array
    {
        return ['receipt_date' => 'date', 'is_late_entry' => 'boolean'];
    }
}
