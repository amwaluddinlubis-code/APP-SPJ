<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        // BKU/ARKAS Financial Data (keep)
        'fiscal_year_id',
        'fund_source_id',
        'id_kas_umum',
        'no_bukti',
        'transaction_date',
        'description',
        'payment_description',
        'payment_method',
        'payment_reference',
        'activity_code',
        'activity_name',
        'account_code',
        'account_name',
        'recipient_name',
        'vendor_name',
        'vendor_owner',
        'vendor_npwp',
        'invoice_number',
        'invoice_date',
        'invoice_status',
        'signatory_name',
        'signatory_role',
        'gross_amount',
        'ppn',
        'ppn_rate',
        'pph21',
        'pph21_rate',
        'pph22',
        'pph22_rate',
        'pph23',
        'pph23_rate',
        'pph4',
        'pph4_rate',
        'sspd',
        'sspd_rate',
        'tax_total',
        'net_amount',
        'is_siplah',
        'status',
        'source_key',
        'source_hash',
        'source_status',
        'last_seen_sync_run_id',
        'source_missing_since',
        'requires_reconciliation',

        // SPJ Transaction Metadata (keep)
        'spj_category', // SPJ category selected
        'spj_recipient_name', // Override for SPJ recipient
        'receipt_recipient_name', // Manual receipt recipient, may differ from ARKAS/BKU recipient
        'event_name',
        'event_location',
        'event_date',
        'participant_count',

    ];

    public function scopeActiveContext(Builder $query): Builder
    {
        return $query
            ->where('fiscal_year_id', session('active_fiscal_year_id'))
            ->where('fund_source_id', session('active_fund_source_id'));
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function goods(): HasManyThrough
    {
        return $this->hasManyThrough(SpjGoods::class, TransactionItem::class);
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(SpjWorkOrder::class);
    }

    public function workers(): HasManyThrough
    {
        return $this->hasManyThrough(SpjWorker::class, SpjWorkOrder::class, 'transaction_id', 'work_order_id')
            ->orderBy('spj_workers.sort_order')
            ->orderBy('spj_workers.id');
    }

    public function participants(): HasManyThrough
    {
        return $this->hasManyThrough(SpjParticipant::class, TransactionItem::class)
            ->orderBy('spj_participants.sort_order')
            ->orderBy('spj_participants.id');
    }

    public function travels(): HasMany
    {
        return $this->hasMany(SpjTravel::class)->orderBy('sort_order')->orderBy('id');
    }

    public function honors(): HasManyThrough
    {
        return $this->hasManyThrough(SpjHonor::class, TransactionItem::class)
            ->orderBy('spj_honors.sort_order')
            ->orderBy('spj_honors.id');
    }

    public function spjPackage(): HasOne
    {
        return $this->hasOne(SpjPackage::class);
    }

    public function getEffectiveReceiptRecipientNameAttribute(): ?string
    {
        return $this->receipt_recipient_name
            ?: $this->spj_recipient_name
            ?: $this->signatory_name
            ?: $this->recipient_name;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'invoice_date' => 'date', 'event_date' => 'date', 'participant_count' => 'integer', 'source_missing_since' => 'datetime', 'requires_reconciliation' => 'boolean', 'gross_amount' => 'decimal:2', 'ppn' => 'decimal:2', 'ppn_rate' => 'decimal:4', 'pph21' => 'decimal:2', 'pph21_rate' => 'decimal:4', 'pph22' => 'decimal:2', 'pph22_rate' => 'decimal:4', 'pph23' => 'decimal:2', 'pph23_rate' => 'decimal:4', 'pph4' => 'decimal:2', 'pph4_rate' => 'decimal:4', 'sspd' => 'decimal:2', 'sspd_rate' => 'decimal:4', 'tax_total' => 'decimal:2', 'net_amount' => 'decimal:2', 'is_siplah' => 'boolean'];
    }
}
