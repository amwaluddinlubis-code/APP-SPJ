<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolDatabase extends Model
{
    protected $fillable = ['school_id', 'database_path', 'status', 'last_migrated_at'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    protected function casts(): array
    {
        return ['last_migrated_at' => 'datetime'];
    }
}
