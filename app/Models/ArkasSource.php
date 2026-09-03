<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArkasSource extends Model
{
    protected $fillable = ['school_id', 'database_path', 'bridge_path', 'database_password', 'last_identity', 'last_synced_at'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    protected function casts(): array
    {
        return ['database_password' => 'encrypted', 'last_synced_at' => 'datetime'];
    }
}
