<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArkasImportRun extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        'profile_id',
        'fiscal_year_id',
        'status',
        'records_read',
        'records_written',
        'records_new',
        'records_changed',
        'records_unchanged',
        'records_removed',
        'message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ArkasImportProfile::class, 'profile_id');
    }
}
