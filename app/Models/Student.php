<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $connection = 'school';

    protected $guarded = [];

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('nisn', 'like', "%{$search}%")->orWhere('nipd', 'like', "%{$search}%")->orWhere('class_name', 'like', "%{$search}%")));
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'school_entry_date' => 'date', 'is_active' => 'boolean', 'special_needs' => 'boolean', 'payload' => 'array', 'last_synced_at' => 'datetime'];
    }
}
