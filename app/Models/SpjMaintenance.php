<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpjMaintenance extends Model
{
    protected $connection = 'school';

    protected $fillable = ['fiscal_year_id', 'name', 'description', 'default_location', 'status'];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(SpjWorkOrder::class, 'maintenance_id');
    }
}
