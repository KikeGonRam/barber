<?php

namespace App\Services\Loyalty;

use App\Models\Client;
use App\Models\LoyaltyTransaction;
use App\Notifications\LoyaltyNotification;

class LoyaltyService
{
    const LEVELS = [
        'nuevo'   => 0,
        'regular' => 5,
        'vip'     => 10,
        'leyenda' => 20,
    ];

    const DISCOUNTS = [
        'nuevo'   => 0,
        'regular' => 5,
        'vip'     => 10,
        'leyenda' => 15,
    ];

    const LEVEL_LABELS = [
        'nuevo'   => 'Caballero',
        'regular' => 'Regular',
        'vip'     => 'V.I.P',
        'leyenda' => 'Leyenda',
    ];

    const LEVEL_ICONS = [
        'nuevo'   => '—',
        'vip'     => 'V',
        'regular' => 'R',
        'leyenda' => 'L',
    ];

    public static function nivelFromCitas(int $citas): string
    {
        if ($citas >= self::LEVELS['leyenda']) return 'leyenda';
        if ($citas >= self::LEVELS['vip'])     return 'vip';
        if ($citas >= self::LEVELS['regular']) return 'regular';
        return 'nuevo';
    }

    public static function nextLevel(string $nivel): ?string
    {
        $map = ['nuevo' => 'regular', 'regular' => 'vip', 'vip' => 'leyenda', 'leyenda' => null];
        return $map[$nivel] ?? null;
    }

    public static function citasForLevel(string $nivel): int
    {
        return self::LEVELS[$nivel] ?? 0;
    }

    public static function discountPct(string $nivel): int
    {
        return self::DISCOUNTS[$nivel] ?? 0;
    }

    public static function applyDiscount(float $price, string $nivel): float
    {
        $pct = self::discountPct($nivel);
        return $pct > 0 ? round($price * (1 - $pct / 100), 2) : $price;
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
        } elseif ($client->nivel === null) {
            $client->update(['nivel' => $newNivel]);
        }

        LoyaltyTransaction::create([
            'client_id'     => (string) $client->id,
            'tipo'          => 'ganado',
            'puntos'        => 10,
            'descripcion'   => 'Cita completada',
            'referencia_id' => $appointmentId,
        ]);
    }

    public function awardResenaPoints(Client $client, string $reviewId): void
    {
        $client->increment('puntos', 5);

        LoyaltyTransaction::create([
            'client_id'     => (string) $client->id,
            'tipo'          => 'ganado',
            'puntos'        => 5,
            'descripcion'   => 'Reseña publicada',
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
            'client_id'   => (string) $client->id,
            'tipo'        => 'canjeado',
            'puntos'      => -$puntos,
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
