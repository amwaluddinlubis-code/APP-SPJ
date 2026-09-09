<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArkasImportProfile extends Model
{
    protected $connection = 'school';

    protected $fillable = ['source_table', 'target_domain', 'label', 'source_key_column', 'year_column', 'fund_source_column', 'source_updated_column', 'sync_mode', 'is_enabled', 'mapping', 'source_columns', 'last_synced_at'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'mapping' => 'array', 'source_columns' => 'array', 'last_synced_at' => 'datetime'];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ArkasImportRun::class, 'profile_id');
    }
}
