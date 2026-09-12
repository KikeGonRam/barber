<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Lista de espera: un cliente se anota para un barbero+servicio+fecha
 * específico cuando ya no hay horarios disponibles ese día. Si una cita de
 * ese mismo barbero+servicio+fecha se cancela o se reagenda a otro día,
 * WaitlistService::notifyIfAny() avisa a todos los anotados a la vez —
 * el primero en reservar de verdad se lo queda, el backend de citas sigue
 * siendo la única autoridad sobre el hueco real.
 */
class Waitlist extends Model
{
    use HasFactory;

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_NOTIFICADO = 'notificado';

    public const ESTADO_RESERVADO = 'reservado';

    public const ESTADO_CANCELADO = 'cancelado';

    public const ESTADO_EXPIRADO = 'expirado';

    protected $fillable = [
        'client_id',
        'barber_id',
        'service_id',
        'fecha',
        'estado',
        'activa',
        'notificado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'activa' => 'boolean',
            'notificado_en' => 'datetime',
        ];
    }

    /**
     * `activa` es un campo derivado de `estado` (nunca se asigna a mano),
     * igual patrón que Appointment::bloquea_horario / Payment::bloquea_cita:
     * MongoDB no permite $in/$nin dentro de un partialFilterExpression, solo
     * igualdad, así que el índice único de la migración necesita esta
     * bandera booleana para expresar "sigue esperando" (activo o notificado)
     * como una simple igualdad.
     */
    protected static function booted(): void
    {
        static::saving(function (self $entry) {
            $entry->activa = in_array($entry->estado, [self::ESTADO_ACTIVO, self::ESTADO_NOTIFICADO], true);
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
