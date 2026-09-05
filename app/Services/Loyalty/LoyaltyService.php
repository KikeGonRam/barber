<?php

namespace App\Services\Loyalty;

use App\Models\Client;
use App\Models\LoyaltyTransaction;
use App\Notifications\LoyaltyLevelDowngradedNotification;
use App\Notifications\LoyaltyNotification;
use App\Notifications\LoyaltyPointsExpiredNotification;

/**
 * Orquesta el programa de lealtad: niveles por número de citas completadas,
 * descuentos asociados, otorgamiento/canje de puntos y registro de transacciones.
 * Usado por el flujo de citas (al completarse), reseñas y los dashboards de cliente/admin.
 */
class LoyaltyService
{
    const LEVELS = [
        'nuevo' => 0,
        'regular' => 5,
        'vip' => 10,
        'leyenda' => 20,
    ];

    const DISCOUNTS = [
        'nuevo' => 0,
        'regular' => 5,
        'vip' => 10,
        'leyenda' => 15,
    ];

    const LEVEL_LABELS = [
        'nuevo' => 'Caballero',
        'regular' => 'Regular',
        'vip' => 'V.I.P',
        'leyenda' => 'Leyenda',
    ];

    const LEVEL_ICONS = [
        'nuevo' => '—',
        'vip' => 'V',
        'regular' => 'R',
        'leyenda' => 'L',
    ];

    // Ciclo de vida por inactividad (ApplyLoyaltyInactivityCommand, corre
    // diario): cuantos dias sin una cita completada antes de bajar un nivel,
    // y antes de que el saldo de puntos caduque por completo. Decision del
    // dueno del proyecto (2026-09-05): un VIP inactivo debe poder bajar,
    // igual que los puntos deben caducar como en Spin Premia (OXXO) — no son
    // beneficios de por vida, hay que seguir viniendo para conservarlos.
    const DIAS_BAJA_DE_NIVEL = 180;

    const DIAS_CADUCIDAD_PUNTOS = 365;

    // Orden de nivel de menor a mayor, usado para calcular cuantos escalones
    // baja un cliente segun cuanto tiempo lleve inactivo (ver
    // levelAfterInactivity()).
    const LEVEL_ORDER = ['nuevo', 'regular', 'vip', 'leyenda'];

    /**
     * Calcula el nivel de lealtad correspondiente a un número de citas completadas.
     */
    public static function nivelFromCitas(int $citas): string
    {
        if ($citas >= self::LEVELS['leyenda']) {
            return 'leyenda';
        }
        if ($citas >= self::LEVELS['vip']) {
            return 'vip';
        }
        if ($citas >= self::LEVELS['regular']) {
            return 'regular';
        }

        return 'nuevo';
    }

    /**
     * Siguiente nivel en la progresión, o null si ya está en el nivel máximo (leyenda).
     */
    public static function nextLevel(string $nivel): ?string
    {
        $map = ['nuevo' => 'regular', 'regular' => 'vip', 'vip' => 'leyenda', 'leyenda' => null];

        return $map[$nivel] ?? null;
    }

    /**
     * Nivel anterior en la progresión, o null si ya está en el nivel mínimo (nuevo).
     */
    public static function previousLevel(string $nivel): ?string
    {
        $map = ['regular' => 'nuevo', 'vip' => 'regular', 'leyenda' => 'vip', 'nuevo' => null];

        return $map[$nivel] ?? null;
    }

    /**
     * Número de citas requeridas para alcanzar un nivel.
     */
    public static function citasForLevel(string $nivel): int
    {
        return self::LEVELS[$nivel] ?? 0;
    }

    /**
     * Porcentaje de descuento asociado a un nivel de lealtad.
     */
    public static function discountPct(string $nivel): int
    {
        return self::DISCOUNTS[$nivel] ?? 0;
    }

    /**
     * Aplica el descuento por nivel a un precio, redondeado a 2 decimales.
     */
    public static function applyDiscount(float $price, string $nivel): float
    {
        $pct = self::discountPct($nivel);

        return $pct > 0 ? round($price * (1 - $pct / 100), 2) : $price;
    }

    /**
     * Máximo de puntos canjeables en una sola visita: 1 punto = $1 MXN, con
     * tope del 50% del total (ya con el descuento de nivel aplicado) para
     * que el cliente siempre pague al menos la mitad en efectivo/tarjeta, y
     * nunca más de lo que tenga acumulado.
     */
    public static function maxRedeemablePoints(float $totalDespuesDeNivel, int $puntosDisponibles): int
    {
        $topePorMitad = (int) floor($totalDespuesDeNivel * 0.5);

        return max(0, min($topePorMitad, $puntosDisponibles));
    }

    /**
     * Nivel resultante de aplicar la regla de inactividad: por cada bloque
     * completo de DIAS_BAJA_DE_NIVEL sin una cita completada, se baja un
     * escalon respecto al nivel que el cliente tendria por su historial
     * completo de citas (nivelFromCitas). Es un calculo directo (no un
     * decremento acumulativo dia a dia), asi que correr esto todos los dias
     * es seguro: siempre converge al nivel correcto para la inactividad
     * actual, nunca seguira bajando de mas solo por ejecutarse repetidas
     * veces. Si el cliente vuelve a agendar, awardCitaPoints() ya recalcula
     * el nivel desde total_citas de forma independiente, asi que retoma su
     * nivel ganado sin necesidad de "revertir" nada aqui.
     */
    public static function levelAfterInactivity(int $totalCitas, int $diasSinVisita): string
    {
        $nivelGanado = self::nivelFromCitas($totalCitas);
        $indiceGanado = array_search($nivelGanado, self::LEVEL_ORDER, true);
        $escalonesABajar = intdiv(max(0, $diasSinVisita), self::DIAS_BAJA_DE_NIVEL);
        $indiceFinal = max(0, $indiceGanado - $escalonesABajar);

        return self::LEVEL_ORDER[$indiceFinal];
    }

    /**
     * Aplica las dos reglas de ciclo de vida por inactividad a un cliente:
     * baja de nivel (180+ dias sin cita completada) y caducidad total del
     * saldo de puntos (365+ dias). Pensado para llamarse una vez por cliente
     * y por dia desde ApplyLoyaltyInactivityCommand — nunca desde el flujo
     * normal de otorgar/canjear puntos.
     *
     * @return array{downgraded: bool, points_expired: bool, new_level: ?string}
     */
    public function applyInactivityLifecycle(Client $client, int $diasSinVisita): array
    {
        $result = ['downgraded' => false, 'points_expired' => false, 'new_level' => null];

        if ($diasSinVisita >= self::DIAS_CADUCIDAD_PUNTOS && (int) $client->puntos > 0) {
            $puntosPerdidos = (int) $client->puntos;
            $client->update(['puntos' => 0]);

            LoyaltyTransaction::create([
                'client_id' => (string) $client->id,
                'tipo' => 'canjeado',
                'puntos' => -$puntosPerdidos,
                'descripcion' => 'Puntos vencidos por '.self::DIAS_CADUCIDAD_PUNTOS.'+ dias sin actividad',
            ]);

            $result['points_expired'] = true;

            try {
                $client->user?->notify(new LoyaltyPointsExpiredNotification($puntosPerdidos));
            } catch (\Throwable) {
            }
        }

        $nivelActual = $client->getRawOriginal('nivel') ?? 'nuevo';
        $nivelCorrespondiente = self::levelAfterInactivity((int) $client->total_citas, $diasSinVisita);
        $esBajaReal = array_search($nivelCorrespondiente, self::LEVEL_ORDER, true)
            < array_search($nivelActual, self::LEVEL_ORDER, true);

        // Solo baja, nunca sube: si el calculo diera un nivel mayor al actual
        // (no deberia pasar en la practica, pero por seguridad), se ignora
        // aqui — cualquier subida de nivel real pasa por awardCitaPoints()
        // al completarse una cita, no por este job de inactividad.
        if ($esBajaReal) {
            $client->update(['nivel' => $nivelCorrespondiente]);
            $result['downgraded'] = true;
            $result['new_level'] = $nivelCorrespondiente;

            try {
                $client->user?->notify(new LoyaltyLevelDowngradedNotification($nivelActual, $nivelCorrespondiente));
            } catch (\Throwable) {
            }
        }

        return $result;
    }

    public function awardCitaPoints(Client $client, string $appointmentId): void
    {
        $previousNivel = $client->nivel ?? 'nuevo';

        $client->increment('puntos', 10);
        $client->increment('total_citas', 1);
        $client->refresh();

        $newNivel = self::nivelFromCitas((int) $client->total_citas);

        if ($newNivel !== ($client->nivel ?? 'nuevo')) {
            $client->update(['nivel' => $newNivel]);

            $this->notifyLevelUp($client, $previousNivel, $newNivel);
        } elseif ($client->getRawOriginal('nivel') === null) {
            // El accessor de Client::nivel ya convierte null -> 'nuevo', así que
            // $client->nivel nunca es null aquí. Hay que mirar el valor crudo del
            // documento para detectar clientes viejos/incompletos de Mongo que
            // nunca tuvieron 'nivel' guardado, y así backfillearlo de una vez.
            $client->update(['nivel' => $newNivel]);
        }

        LoyaltyTransaction::create([
            'client_id' => (string) $client->id,
            'tipo' => 'ganado',
            'puntos' => 10,
            'descripcion' => 'Cita completada',
            'referencia_id' => $appointmentId,
        ]);
    }

    // Puntos de regalo por cumpleaños, otorgados por SendBirthdayGreetingsCommand.
    // La descripcion se comparte como constante porque el comando la usa para
    // detectar si un cliente ya recibio su regalo este mismo año (evita
    // duplicarlo si el comando se corre dos veces el mismo dia).
    const BIRTHDAY_POINTS = 20;

    const BIRTHDAY_TRANSACTION_DESCRIPTION = 'Regalo de cumpleaños';

    public function awardBirthdayPoints(Client $client): void
    {
        $client->increment('puntos', self::BIRTHDAY_POINTS);

        LoyaltyTransaction::create([
            'client_id' => (string) $client->id,
            'tipo' => 'ganado',
            'puntos' => self::BIRTHDAY_POINTS,
            'descripcion' => self::BIRTHDAY_TRANSACTION_DESCRIPTION,
        ]);
    }

    public function awardResenaPoints(Client $client, string $reviewId): void
    {
        $client->increment('puntos', 5);

        LoyaltyTransaction::create([
            'client_id' => (string) $client->id,
            'tipo' => 'ganado',
            'puntos' => 5,
            'descripcion' => 'Reseña publicada',
            'referencia_id' => $reviewId,
        ]);
    }

    public function redeemPoints(Client $client, int $puntos, string $descripcion): bool
    {
        if ((int) $client->puntos < $puntos) {
            return false;
        }

        $client->decrement('puntos', $puntos);

        LoyaltyTransaction::create([
            'client_id' => (string) $client->id,
            'tipo' => 'canjeado',
            'puntos' => -$puntos,
            'descripcion' => $descripcion,
        ]);

        return true;
    }

    private function notifyLevelUp(Client $client, string $from, string $to): void
    {
        try {
            $client->user?->notify(new LoyaltyNotification(
                level: $to,
                previousLevel: $from,
                discount: self::discountPct($to),
            ));
        } catch (\Throwable) {
        }
    }
}
