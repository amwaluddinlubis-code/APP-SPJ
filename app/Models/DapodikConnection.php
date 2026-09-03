<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DapodikConnection extends Model
{
    protected $connection = 'school';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'is_active' => 'boolean', 'last_synced_at' => 'datetime'];
    }
}
