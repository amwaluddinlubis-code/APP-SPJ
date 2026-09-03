<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class School extends Model
{
    protected $fillable = ['npsn', 'school_code', 'name', 'address', 'district', 'regency', 'province', 'letterhead_path'];

    public function databaseRecord(): HasOne
    {
        return $this->hasOne(SchoolDatabase::class);
    }
}
