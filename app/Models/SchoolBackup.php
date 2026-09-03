<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolBackup extends Model
{
    protected $fillable = ['school_id', 'file_path', 'file_name', 'file_size', 'reason', 'created_by'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
