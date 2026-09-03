<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjGoods extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        'transaction_item_id',
        'order_number',
        'order_date',
        'bap_number',
        'bap_date',
        'bast_number',
        'bast_date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'transaction_item_id');
    }

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'bap_date' => 'date',
            'bast_date' => 'date',
        ];
    }
}
