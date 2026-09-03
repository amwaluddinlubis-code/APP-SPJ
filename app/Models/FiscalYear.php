<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    protected $connection = 'school';

    protected $fillable = ['year', 'fund_source', 'fund_source_id', 'is_active'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
