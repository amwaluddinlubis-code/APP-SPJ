<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjV2Settlement extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        'spj_package_id',
        'transaction_id',
        'effective_fiscal_year_id',
        'effective_fund_source_id',
        'source_id',
        'effective_quarter',
        'gross_amount',
        'paid_amount',
        'tax_amount',
        'net_amount',
        'status',
        'snapshot',
        'settled_at',
        'settled_by',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpjPackage::class, 'spj_package_id');
    }

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'snapshot' => 'array',
            'settled_at' => 'datetime',
        ];
    }
}
