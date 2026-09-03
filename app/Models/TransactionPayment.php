<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionPayment extends Model
{
    protected $connection = 'school';

    protected $fillable = ['transaction_id', 'scope_key', 'payment_sequence', 'payment_date', 'gross_amount', 'tax_amount', 'net_amount', 'payment_method', 'payment_reference', 'status', 'is_late_entry'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'gross_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'net_amount' => 'decimal:2', 'is_late_entry' => 'boolean'];
    }
}
