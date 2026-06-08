<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class BarberSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'barber_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_working',
    ];

    protected $casts = [
        'is_working' => 'boolean',
    ];

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }
}
