<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentNumberFormat extends Model
{
    protected $connection = 'school';

    protected $fillable = ['fiscal_year_id', 'document_type', 'format_pattern', 'reset_period', 'padding', 'is_active'];

    protected function casts(): array
    {
        return ['padding' => 'integer', 'is_active' => 'boolean'];
    }
}
