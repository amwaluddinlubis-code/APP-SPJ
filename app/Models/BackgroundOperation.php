<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackgroundOperation extends Model
{
    protected $fillable = [
        'school_id', 'fiscal_year_id', 'requested_by', 'type', 'status',
        'progress', 'message', 'result', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['result' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
