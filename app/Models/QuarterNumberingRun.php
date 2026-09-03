<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarterNumberingRun extends Model
{
    protected $connection = 'school';

    protected $fillable = ['fiscal_period_closure_id', 'fiscal_year_id', 'quarter', 'status', 'document_types', 'numbered_count', 'skipped_count', 'failed_count', 'error_message', 'started_by', 'started_at', 'completed_at'];

    public function closure(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriodClosure::class, 'fiscal_period_closure_id');
    }

    protected function casts(): array
    {
        return ['quarter' => 'integer', 'document_types' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
