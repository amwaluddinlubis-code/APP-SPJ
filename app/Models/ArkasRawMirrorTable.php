<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArkasRawMirrorTable extends Model
{
    protected $table = 'arkas_raw_mirror_tables';

    protected $connection = 'school';

    protected $fillable = ['source_id', 'source_table', 'schema', 'schema_hash', 'row_count', 'status', 'last_seen_at', 'last_synced_at', 'last_error'];

    public function rows(): HasMany
    {
        return $this->hasMany(ArkasRawMirrorRow::class, 'mirror_table_id');
    }

    protected function casts(): array
    {
        return [
            'schema' => 'array', 'row_count' => 'integer', 'last_seen_at' => 'datetime', 'last_synced_at' => 'datetime',
        ];
    }
}
