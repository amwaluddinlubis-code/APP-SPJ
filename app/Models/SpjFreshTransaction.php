<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
}
