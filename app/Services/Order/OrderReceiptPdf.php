<?php

namespace App\Services\Order;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Comprobante en PDF de un pedido de la tienda. Lo usan la descarga bajo demanda del cliente
 * (OrderController::receiptLink) y el correo de «pedido entregado», para que ambos sean el mismo documento.
 */
final class OrderReceiptPdf
{
    public static function make(Order $order): \Barryvdh\DomPDF\PDF
    {
        $order->loadMissing('client.user');

        return Pdf::loadView('pdf.order-receipt', [
            'folio' => $order->getAttribute('folio'),
            'emitido' => optional($order->getAttribute('entregado_en') ?? $order->getAttribute('created_at'))->format('d/m/Y'),
            'cliente' => data_get($order, 'client.user.name') ?? 'Cliente',
            'items' => $order->getAttribute('items') ?? [],
            'total' => (float) $order->getAttribute('total'),
            'metodo' => ucfirst((string) ($order->getAttribute('metodo_pago') ?? '—')),
        ]);
    }
}
