<?php

namespace App\Services\Loyalty;

use App\Models\Client;
use App\Models\LoyaltyTransaction;
use App\Notifications\LoyaltyNotification;

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
