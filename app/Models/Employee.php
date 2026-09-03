<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        'source_type', 'source_key', 'name', 'nip', 'nik', 'nuptk', 'gender',
        'employment_status', 'staff_type', 'position', 'npwp', 'bank_name',
        'bank_account', 'is_active', 'payload', 'dapodik_id', 'normalized_name',
        'birth_place', 'birth_date', 'religion', 'last_education', 'last_study_field',
        'rank_group', 'is_primary_school', 'last_synced_at',
    ];

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query, string $search): void {
            $query->where(function (Builder $query) use ($search): void {
                foreach (['name', 'nip', 'nik', 'nuptk', 'position', 'staff_type'] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'payload' => 'array', 'birth_date' => 'date', 'is_primary_school' => 'boolean', 'last_synced_at' => 'datetime'];
    }
}
