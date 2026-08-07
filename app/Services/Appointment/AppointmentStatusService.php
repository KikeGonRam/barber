<?php

namespace App\Services\Appointment;

use App\Exceptions\Domain\InvalidAppointmentTransitionException;
use App\Models\Appointment;

/**
 * Maquina de estados de las citas. Centraliza las transiciones validas y quien
 * puede hacerlas, para que el flujo sea estricto y coherente en todos los
 * puntos de entrada (web, API movil, dashboard del barbero).
 *
 * Flujo:
 *   pendiente  -> confirmada | cancelada
 *   confirmada -> en_proceso | completada | no_asistio | cancelada
 *   en_proceso -> completada | cancelada
 *   completada | cancelada | no_asistio  (terminales)
 */
class AppointmentStatusService
{
    public const TRANSITIONS = [
        'pendiente' => ['confirmada', 'cancelada'],
        'confirmada' => ['en_proceso', 'completada', 'no_asistio', 'cancelada'],
        'en_proceso' => ['completada', 'cancelada'],
        'completada' => [],
        'cancelada' => [],
        'no_asistio' => [],
    ];

    /**
     * Estados desde los que SI se puede cobrar (nunca pendiente).
     */
    public const CHARGEABLE = ['confirmada', 'en_proceso', 'completada'];

    /**
     * Roles autorizados por transicion (destino => roles). El barbero solo
     * puede sobre SUS citas (eso se valida en el controlador).
     */
    private const ROLE_MAP = [
        'confirmada' => ['barbero', 'recepcionista', 'administrador'],
        'en_proceso' => ['barbero', 'recepcionista', 'administrador'],
        'completada' => ['barbero', 'recepcionista', 'administrador'],
        'no_asistio' => ['barbero', 'recepcionista', 'administrador'],
        'cancelada' => ['barbero', 'recepcionista', 'administrador', 'cliente'],
    ];

    /**
     * Indica si el cambio de estado $from -> $to esta permitido por la
     * maquina de estados (sin considerar rol del usuario).
     */
    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Lista los estados destino validos desde el estado actual (para
     * poblar opciones en la UI, por ejemplo).
     */
    public function allowedFor(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    /**
     * Indica si el rol dado tiene permiso para poner la cita en el estado
     * destino $to (independiente de si la transicion en si es legal).
     */
    public function roleCanSet(string $to, ?string $role): bool
    {
        return $role !== null && in_array($role, self::ROLE_MAP[$to] ?? [], true);
    }

    /**
     * Gate de cobro: solo se puede cobrar una cita que este confirmada,
     * en proceso o completada (nunca pendiente ni cancelada).
     */
    public function isChargeable(string $estado): bool
    {
        return in_array($estado, self::CHARGEABLE, true);
    }

    /**
     * Aplica la transicion validando que sea legal. Lanza si no lo es.
     * No dispara efectos externos (lealtad/notificaciones) — eso lo hace el
     * llamador segun su contexto.
     */
    public function transition(Appointment $appointment, string $to): void
    {
        $from = (string) $appointment->estado;

        if ($from === $to) {
            return;
        }

        if (! $this->canTransition($from, $to)) {
            throw new InvalidAppointmentTransitionException(
                "No se puede pasar la cita de '{$from}' a '{$to}'."
            );
        }

        $updates = ['estado' => $to];

        if ($to === 'cancelada') {
            $updates['cancelada_en'] = now();
        }

        if ($to === 'en_proceso') {
            $updates['servicio_iniciado_en'] = now();
        }

        $appointment->update($updates);
    }
}
