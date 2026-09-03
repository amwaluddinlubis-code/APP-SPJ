<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjDocument extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        'spj_package_id', 'document_template_id', 'replaces_document_id', 'document_type', 'scope_key',
        'document_number', 'sequence_number', 'document_date', 'event_date', 'status',
        'is_late_entry', 'snapshot', 'template_snapshot', 'template_hash', 'rendered_hash', 'numbered_at', 'finalized_at', 'finalized_by',
        'cancelled_at', 'cancelled_by', 'cancellation_reason',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpjPackage::class, 'spj_package_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_document_id');
    }

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer', 'document_date' => 'date', 'event_date' => 'date',
            'is_late_entry' => 'boolean', 'snapshot' => 'array', 'template_snapshot' => 'array', 'numbered_at' => 'datetime', 'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
