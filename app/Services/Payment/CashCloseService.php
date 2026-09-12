<?php

namespace App\Services\Payment;

use App\Models\ClientPackage;
use App\Models\GiftCard;
use App\Models\MembershipInvoice;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;

/**
 * Calcula el dinero realmente recibido en un día, por método de pago.
 *
 * Cinco fuentes, no una: los cobros de citas viven en Payment, las ventas
 * de la tienda NO generan Payment -- Order lleva su propio total y
 * metodo_pago (ver App\Models\Order) -- ni la compra de un paquete
 * prepagado, ni la de una gift card, ni un cobro de membresía recurrente son
 * un Payment (no están ligados a ninguna cita, ver
 * PackageService/GiftCardService/MembershipService). Un corte que solo
 * mirara pagos subreportaría ventas de producto, paquetes, gift cards Y
 * membresías.
 *
 * Qué cuenta como dinero recibido:
 *  - Payment con estado 'verificado'. Nunca 'pendiente_verificacion' (una
 *    transferencia sin revisar todavía no está en la cuenta) ni 'rechazado'.
 *  - Order con estado 'entregado', fechada por entregado_en, que es cuando
 *    se cobra en el mostrador (ver OrderController::deliver()).
 *  - ClientPackage / GiftCard: toda compra registrada cuenta como dinero
 *    recibido de inmediato -- este primer alcance solo admite
 *    efectivo/tarjeta (ver PackageService/GiftCardService), nunca
 *    transferencia pendiente de revisar. Cuando después se REDIME una gift
 *    card contra un Payment, ese dinero no se cuenta dos veces: el Payment
 *    ya nace con el monto reducido por lo que la tarjeta cubrió, y la
 *    compra original de la tarjeta ya se contó el día que se compró.
 *  - MembershipInvoice: cada alta/renovación de membresía que Stripe cobró
 *    con éxito, siempre método 'tarjeta' (Stripe Subscriptions es 100%
 *    tarjeta, sin equivalente en efectivo/transferencia).
 */
class CashCloseService
{
    /**
     * Desglose del día: totales por método, propinas y gran total.
     *
     * @return array{por_metodo: array<string, float>, propinas: float, total: float, pagos: int, pedidos: int, paquetes: int, gift_cards: int, membresias: int}
     */
    public function expectedFor(Carbon $date): array
    {
        // startOfDay/endOfDay con objetos Carbon, nunca strings: whereBetween
        // contra un campo date-cast en MongoDB no hace match con strings
        // (bug recurrente documentado en el proyecto).
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $porMetodo = [];
        $propinas = 0.0;

        $payments = Payment::where('estado', Payment::ESTADO_VERIFICADO)
            ->whereBetween('created_at', [$start, $end])
            ->get(['metodo_pago', 'monto', 'propina']);

        foreach ($payments as $payment) {
            $metodo = $this->methodKey($payment->metodo_pago);
            $propina = (float) ($payment->propina ?? 0);
            $propinas += $propina;
            $porMetodo[$metodo] = ($porMetodo[$metodo] ?? 0.0) + (float) ($payment->monto ?? 0) + $propina;
        }

        $orders = Order::where('estado', 'entregado')
            ->whereBetween('entregado_en', [$start, $end])
            ->get(['metodo_pago', 'total']);

        foreach ($orders as $order) {
            $metodo = $this->methodKey($order->metodo_pago);
            $porMetodo[$metodo] = ($porMetodo[$metodo] ?? 0.0) + (float) ($order->total ?? 0);
        }

        $packages = ClientPackage::whereBetween('comprado_en', [$start, $end])
            ->get(['metodo_pago', 'precio_pagado']);

        foreach ($packages as $package) {
            $metodo = $this->methodKey($package->metodo_pago);
            $porMetodo[$metodo] = ($porMetodo[$metodo] ?? 0.0) + (float) ($package->precio_pagado ?? 0);
        }

        $giftCards = GiftCard::whereBetween('comprado_en', [$start, $end])
            ->get(['metodo_pago', 'monto_inicial']);

        foreach ($giftCards as $giftCard) {
            $metodo = $this->methodKey($giftCard->metodo_pago);
            $porMetodo[$metodo] = ($porMetodo[$metodo] ?? 0.0) + (float) ($giftCard->monto_inicial ?? 0);
        }

        $membershipInvoices = MembershipInvoice::whereBetween('pagado_en', [$start, $end])->get(['monto']);

        foreach ($membershipInvoices as $invoice) {
            $porMetodo['tarjeta'] = ($porMetodo['tarjeta'] ?? 0.0) + (float) ($invoice->monto ?? 0);
        }

        $porMetodo = array_map(fn (float $v) => round($v, 2), $porMetodo);
        ksort($porMetodo);

        return [
            'por_metodo' => $porMetodo,
            'propinas' => round($propinas, 2),
            'total' => round(array_sum($porMetodo), 2),
            'pagos' => $payments->count(),
            'pedidos' => $orders->count(),
            'paquetes' => $packages->count(),
            'gift_cards' => $giftCards->count(),
            'membresias' => $membershipInvoices->count(),
        ];
    }

    /**
     * Agrupa por método tolerando datos reales: un registro viejo puede traer
     * el método vacío o nulo, y ese dinero no se puede perder del corte -- cae
     * en "desconocido" para que quien cierra lo vea y lo investigue.
     */
    private function methodKey(mixed $metodo): string
    {
        $value = is_string($metodo) ? trim($metodo) : '';

        return $value !== '' ? $value : 'desconocido';
    }

    /**
     * Efectivo esperado en el cajón: solo lo cobrado en efectivo. Tarjeta y
     * transferencia no pasan por la caja física, así que no entran en el
     * arqueo aunque sí en el total del día.
     */
    public function expectedCash(array $expected): float
    {
        return round((float) ($expected['por_metodo']['efectivo'] ?? 0), 2);
    }
}
