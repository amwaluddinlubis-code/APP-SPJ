<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $connection = 'school';

    protected $fillable = ['goods_receipt_id', 'transaction_item_id', 'quantity_received', 'amount_received'];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class);
    }

    protected function casts(): array
    {
        return ['quantity_received' => 'decimal:4', 'amount_received' => 'decimal:2'];
    }
}
