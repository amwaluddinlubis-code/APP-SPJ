<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpjWorkOrder extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        'maintenance_id',
        'transaction_id',
        'expense_type',
        'work_description',
        'spk_number',
        'spk_date',
        'rab_number',
        'rab_date',
        'work_location',
        'work_started_at',
        'work_completed_at',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(SpjMaintenance::class, 'maintenance_id');
    }

    public function workers(): HasMany
    {
        return $this->hasMany(SpjWorker::class, 'work_order_id')->orderBy('sort_order')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'spk_date' => 'date',
            'rab_date' => 'date',
            'work_started_at' => 'date',
            'work_completed_at' => 'date',
        ];
    }
}
