<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplate extends Model
{
    protected $connection = 'school';

    protected $fillable = ['fiscal_year_id', 'document_type', 'name', 'format', 'file_path', 'applicable_categories', 'is_active'];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'applicable_categories' => 'array'];
    }
}
