<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpjPackage extends Model
{
    protected $connection = 'school';

    protected $fillable = ['transaction_id', 'document_number', 'quarter_code', 'semester_code', 'phase_code', 'status', 'is_late_entry', 'numbered_at', 'generated_at', 'snapshot', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'unlocked_at', 'unlocked_by', 'unlock_reason'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SpjDocument::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['DRAFT', 'READY'], true);
    }

    protected function casts(): array
    {
        return ['is_late_entry' => 'boolean', 'numbered_at' => 'datetime', 'generated_at' => 'datetime', 'snapshot' => 'array', 'finalized_at' => 'datetime', 'cancelled_at' => 'datetime', 'unlocked_at' => 'datetime'];
    }
}
