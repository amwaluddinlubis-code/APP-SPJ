<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArkasRawMirrorRow extends Model
{
    protected $table = 'arkas_raw_mirror_rows';

    protected $connection = 'school';

    protected $fillable = ['mirror_table_id', 'source_key', 'ordinal', 'payload', 'payload_hash'];

    public function mirrorTable(): BelongsTo
    {
        return $this->belongsTo(ArkasRawMirrorTable::class, 'mirror_table_id');
    }

    protected function casts(): array
    {
        return ['ordinal' => 'integer', 'payload' => 'array'];
    }
}
