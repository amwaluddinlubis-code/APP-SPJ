<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjHonor extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        'transaction_item_id',
        'name',
        'nip',
        'nik',
        'npwp',
        'position',
        'golongan',
        'honor_months',
        'rate_per_unit',
        'gross_amount',
        'tax_rate',
        'tax_amount',
        'net_amount',
        'bank_name',
        'bank_account',
        'sort_order',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'transaction_item_id');
    }

    protected function casts(): array
    {
        return [
            'honor_months' => 'decimal:2',
            'rate_per_unit' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }
}
