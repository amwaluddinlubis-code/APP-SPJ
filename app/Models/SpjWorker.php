<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjWorker extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        'work_order_id',
        'name',
        'nik',
        'phone',
        'address',
        'job_description',
        'work_days',
        'daily_rate',
        'amount',
        'is_receipt_recipient',
        'notes',
        'sort_order',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(SpjWorkOrder::class, 'work_order_id');
    }

    protected function casts(): array
    {
        return [
            'work_days' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_receipt_recipient' => 'boolean',
        ];
    }
}
