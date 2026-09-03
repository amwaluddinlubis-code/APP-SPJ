<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjParticipant extends Model
{
    protected $connection = 'school';

    protected $fillable = ['transaction_item_id', 'name', 'position', 'portions', 'sort_order'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'transaction_item_id');
    }

    protected function casts(): array
    {
        return ['portions' => 'decimal:2'];
    }
}
