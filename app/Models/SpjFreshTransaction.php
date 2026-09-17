<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class SpjFreshTransaction extends Model
{
    protected $table = 'spj_fresh_transactions';

    protected $connection = 'school';

    protected $fillable = [
        'fiscal_year_id', 'fund_source_id', 'source_id', 'source_table', 'source_key',
        'raw_mirror_row_id', 'spj_category', 'payment_description', 'payment_method',
        'payment_reference', 'receipt_recipient_name', 'source_status',
        'requires_reconciliation', 'source_missing_since',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SpjFreshTransactionItem::class);
    }

    public function package(): HasOne
    {
        return $this->hasOne(SpjFreshPackage::class);
    }

    public function spjPackage(): HasOne
    {
        return $this->package();
    }

    public function rawMirrorRow(): BelongsTo
    {
        return $this->belongsTo(ArkasRawMirrorRow::class, 'raw_mirror_row_id');
    }

    protected function casts(): array
    {
        return [
            'requires_reconciliation' => 'boolean',
            'source_missing_since' => 'datetime',
        ];
    }

    public function getNoBuktiAttribute(): ?string
    {
        return $this->sourceValue(['no_bukti', 'nomor_bukti']);
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->sourceValue(['uraian', 'description']);
    }

    public function getAccountCodeAttribute(): ?string
    {
        return $this->sourceValue(['kode_rekening', 'account_code']);
    }

    public function getActivityCodeAttribute(): ?string
    {
        return $this->sourceValue(['kode_kegiatan', 'activity_code']);
    }

    public function getRecipientNameAttribute(): ?string
    {
        return $this->sourceValue(['nama_penerima', 'penerima', 'recipient_name']);
    }

    public function getEffectiveReceiptRecipientNameAttribute(): ?string
    {
        return $this->receipt_recipient_name ?: $this->recipient_name;
    }

    public function getTransactionDateAttribute(): ?Carbon
    {
        $value = $this->sourceValue(['tanggal_transaksi', 'tanggal']);

        return filled($value) ? Carbon::parse($value) : null;
    }

    public function getGrossAmountAttribute(): float
    {
        return (float) ($this->sourceValue(['jumlah', 'nilai', 'nominal', 'saldo']) ?: 0);
    }

    public function getTaxTotalAttribute(): float
    {
        return (float) ($this->sourceValue(['total_pajak', 'pajak', 'tax_total']) ?: 0);
    }

    /** @param array<int, string> $keys */
    private function sourceValue(array $keys): ?string
    {
        $payload = $this->rawMirrorRow?->payload ?? [];
        foreach ($keys as $key) {
            if (filled($payload[$key] ?? null)) {
                return (string) $payload[$key];
            }
        }

        return null;
    }
}
