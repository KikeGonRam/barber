<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Horario de trabajo de un barbero para un dia de la semana (day_of_week).
 * is_working=false representa un dia libre/no laborable; start_time/end_time
 * definen la ventana de atencion usada para calcular disponibilidad de citas.
 */
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

    // Barbero dueño de este horario.
    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }
}
