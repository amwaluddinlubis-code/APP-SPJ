<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpjFreshPackage extends Model
{
    protected $table = 'spj_fresh_packages';

    protected $connection = 'school';

    protected $fillable = [
        'spj_fresh_transaction_id', 'quarter_code', 'semester_code', 'phase_code', 'status',
        'document_number', 'numbered_at', 'generated_at', 'finalized_at', 'finalized_by',
        'cancelled_at', 'cancelled_by', 'cancellation_reason', 'snapshot',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SpjFreshTransaction::class, 'spj_fresh_transaction_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SpjFreshDocument::class, 'spj_fresh_package_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['DRAFT', 'READY'], true);
    }

    protected function casts(): array
    {
        return [
            'numbered_at' => 'datetime', 'generated_at' => 'datetime', 'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime', 'snapshot' => 'array',
        ];
    }
}
