<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundSource extends Model
{
    public $incrementing = false;

    protected $connection = 'school';

    protected $table = 'fund_sources';

    protected $fillable = ['id', 'code', 'name', 'is_hidden', 'payload'];

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class, 'fund_source_id');
    }

    protected function casts(): array
    {
        return ['is_hidden' => 'boolean', 'payload' => 'array'];
    }
}
