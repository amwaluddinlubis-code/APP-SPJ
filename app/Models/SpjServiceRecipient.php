<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjServiceRecipient extends Model
{
    protected $connection = 'school';

    protected $fillable = ['transaction_id', 'name', 'npwp', 'service_type', 'service_description', 'quantity', 'unit', 'rental_days', 'daily_rate', 'amount', 'usage_started_at', 'usage_completed_at', 'receipt_number', 'payment_reference', 'agreement_number', 'agreement_date', 'is_receipt_recipient', 'notes', 'sort_order'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'rental_days' => 'decimal:2', 'daily_rate' => 'decimal:2', 'amount' => 'decimal:2', 'usage_started_at' => 'date', 'usage_completed_at' => 'date', 'agreement_date' => 'date', 'is_receipt_recipient' => 'boolean'];
    }
}
