<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjFreshDocument extends Model
{
    protected $table = 'spj_fresh_documents';

    protected $connection = 'school';

    protected $fillable = [
        'spj_fresh_package_id', 'document_type', 'scope_key', 'document_number', 'sequence_number',
        'document_date', 'status', 'snapshot', 'template_snapshot', 'template_hash', 'rendered_hash',
        'numbered_at', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpjFreshPackage::class, 'spj_fresh_package_id');
    }

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer', 'document_date' => 'date', 'snapshot' => 'array',
            'template_snapshot' => 'array', 'numbered_at' => 'datetime', 'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
