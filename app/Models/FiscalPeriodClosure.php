<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalPeriodClosure extends Model
{
    protected $connection = 'school';

    protected $fillable = ['fiscal_year_id', 'quarter', 'status', 'numbered_at', 'numbered_by', 'closed_at', 'closed_by', 'reopened_at', 'reopened_by', 'reopen_reason'];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function numberingRuns(): HasMany
    {
        return $this->hasMany(QuarterNumberingRun::class);
    }

    protected function casts(): array
    {
        return ['quarter' => 'integer', 'numbered_at' => 'datetime', 'closed_at' => 'datetime', 'reopened_at' => 'datetime'];
    }
}
