<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjTravel extends Model
{
    protected $connection = 'school';

    protected $table = 'spj_travels';

    protected $fillable = [
        'transaction_id',
        'traveler_name',
        'destination',
        'purpose',
        'assignment_letter_number',
        'assignment_letter_date',
        'departure_date',
        'return_date',
        'transport_mode',
        'participant_count',
        'amount',
        'notes',
        'sort_order',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'assignment_letter_date' => 'date',
            'return_date' => 'date',
            'participant_count' => 'integer',
            'amount' => 'decimal:2',
        ];
    }
}
