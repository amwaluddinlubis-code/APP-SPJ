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

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
