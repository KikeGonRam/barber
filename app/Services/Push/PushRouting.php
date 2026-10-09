<?php

namespace App\Services\Push;

/**
 * Clasifica cada tipo de notificación (campo "type" de toArray) en un canal de Android y en la
 * pantalla de la app que debe abrirse al tocarla. La app valida "route" contra su propia lista,
 * así que un valor desconocido simplemente abre el inicio.
 */
final class PushRouting
{
    /** Canales que la app Android crea al arrancar (ver PushChannels.kt). */
    public const CHANNELS = ['citas', 'pagos', 'pedidos', 'fidelidad', 'promociones', 'operacion'];

    private const MAP = [
        'appointment' => ['citas', 'appointments'],
        'review_request' => ['citas', 'appointments'],
        'payment' => ['pagos', 'payments'],
        'transfer_receipt' => ['pagos', 'payments'],
        'membership_invoice' => ['pagos', 'payments'],
        'order_delivered' => ['pedidos', 'orders'],
        'order_expired' => ['pedidos', 'orders'],
        'loyalty_level_up' => ['fidelidad', 'wallet'],
        'loyalty_level_downgraded' => ['fidelidad', 'wallet'],
        'loyalty_points_expired' => ['fidelidad', 'wallet'],
        'raffle_win' => ['fidelidad', 'wallet'],
        'client_birthday' => ['fidelidad', 'wallet'],
        'promotion' => ['promociones', 'notifications'],
        'service_overrun' => ['operacion', 'appointments'],
        'service_ending' => ['operacion', 'barber_agenda'],
        'inventory_low_stock' => ['operacion', 'inventory'],
    ];

    /** @return array{0: string, 1: string} [canal, ruta de la app] */
    public static function for(string $type): array
    {
        return self::MAP[$type] ?? ['citas', 'notifications'];
    }

    public static function channel(?string $channel): string
    {
        return in_array($channel, self::CHANNELS, true) ? $channel : 'citas';
    }
}
