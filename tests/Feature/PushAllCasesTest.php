<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\MembershipInvoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RaffleResult;
use App\Notifications\Appointment\AppointmentNotification;
use App\Notifications\Barber\ReviewRequestNotification;
use App\Notifications\Campaign\PromotionNotification;
use App\Notifications\Channels\FcmPushChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Inventory\InventoryLowStockNotification;
use App\Notifications\Loyalty\ClientBirthdayNotification;
use App\Notifications\Loyalty\LoyaltyLevelDowngradedNotification;
use App\Notifications\Loyalty\LoyaltyNotification;
use App\Notifications\Loyalty\LoyaltyPointsExpiredNotification;
use App\Notifications\Loyalty\RaffleWinNotification;
use App\Notifications\Membership\MembershipInvoiceNotification;
use App\Notifications\Order\OrderDeliveredNotification;
use App\Notifications\Order\OrderExpiredNotification;
use App\Notifications\Payment\PaymentReceiptNotification;
use App\Notifications\Payment\TransferReceiptNotification;
use App\Services\Push\PushRouting;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Push (web + Android) para todos los tipos de aviso: cada notificación lleva el canal de Android,
 * la pantalla de la app y su texto, y solo sale si el usuario activó "push".
 */
class PushAllCasesTest extends TestCase
{
    private function notifiable(bool $push): object
    {
        return new class($push)
        {
            public string $name = 'Cliente';

            public function __construct(private bool $push) {}

            public function wantsNotificationChannel(string $channel): bool
            {
                return $channel === 'push' ? $this->push : in_array($channel, ['in_app', 'promociones'], true);
            }
        };
    }

    /** @return array<int|string, mixed> */
    private function viaOf(Notification $n, object $notifiable): array
    {
        return method_exists($n, 'via') ? $n->via($notifiable) : [];
    }

    /** @return array<string, string> */
    private function payloadOf(Notification $n, object $notifiable): array
    {
        return method_exists($n, 'toWebPush') ? $n->toWebPush($notifiable) : [];
    }

    /** @return array<string, array{0: Notification, 1: string, 2: string}> */
    public static function casos(): array
    {
        $payment = new Payment(['monto' => 250, 'propina' => 0, 'metodo_pago' => 'tarjeta']);
        $order = new Order(['folio' => 'PED-001', 'total' => 300]);
        $prize = new RaffleResult(['premio' => 'Corte gratis']);

        return [
            'cita' => [new AppointmentNotification(new Appointment, 'Asunto', 'Cita confirmada', 'Te esperamos'), 'citas', 'appointments'],
            'reseña' => [new ReviewRequestNotification(new Appointment), 'citas', 'appointments'],
            'pago' => [new PaymentReceiptNotification($payment), 'pagos', 'payments'],
            'transferencia' => [new TransferReceiptNotification($payment, 'rechazado', 'Borroso'), 'pagos', 'payments'],
            'membresía' => [new MembershipInvoiceNotification(new MembershipInvoice(['monto' => 199, 'stripe_invoice_id' => 'in_1abcdef']), 'Básico'), 'pagos', 'payments'],
            'pedido entregado' => [new OrderDeliveredNotification($order), 'pedidos', 'orders'],
            'pedido vencido' => [new OrderExpiredNotification($order), 'pedidos', 'orders'],
            'nivel sube' => [new LoyaltyNotification('oro', 'plata', 10), 'fidelidad', 'wallet'],
            'nivel baja' => [new LoyaltyLevelDowngradedNotification('oro', 'plata'), 'fidelidad', 'wallet'],
            'puntos vencen' => [new LoyaltyPointsExpiredNotification(120), 'fidelidad', 'wallet'],
            'sorteo' => [new RaffleWinNotification($prize), 'fidelidad', 'wallet'],
            'cumpleaños' => [new ClientBirthdayNotification, 'fidelidad', 'wallet'],
            'promoción' => [new PromotionNotification('2x1', 'Solo hoy'), 'promociones', 'notifications'],
            'stock bajo' => [new InventoryLowStockNotification([['nombre' => 'Cera', 'stock_actual' => 1]]), 'operacion', 'inventory'],
        ];
    }

    #[DataProvider('casos')]
    public function test_con_push_activado_agrega_web_y_fcm(Notification $n, string $canal, string $ruta): void
    {
        $channels = $this->viaOf($n, $this->notifiable(true));

        $this->assertContains(WebPushChannel::class, $channels);
        $this->assertContains(FcmPushChannel::class, $channels);
    }

    #[DataProvider('casos')]
    public function test_con_push_apagado_no_agrega_push(Notification $n, string $canal, string $ruta): void
    {
        $channels = $this->viaOf($n, $this->notifiable(false));

        $this->assertNotContains(WebPushChannel::class, $channels);
        $this->assertNotContains(FcmPushChannel::class, $channels);
    }

    #[DataProvider('casos')]
    public function test_el_payload_trae_texto_canal_y_ruta(Notification $n, string $canal, string $ruta): void
    {
        $payload = $this->payloadOf($n, $this->notifiable(true));

        $this->assertNotSame('', $payload['title']);
        $this->assertNotSame('', $payload['body']);
        $this->assertSame($canal, $payload['channel']);
        $this->assertSame($ruta, $payload['route']);
        $this->assertContains($payload['channel'], PushRouting::CHANNELS);
    }

    public function test_promocion_sin_consentimiento_no_se_envia(): void
    {
        $sin = new class
        {
            public function wantsNotificationChannel(string $channel): bool
            {
                return $channel === 'push';
            }
        };

        $this->assertSame([], (new PromotionNotification('2x1', 'Solo hoy'))->via($sin));
    }

    public function test_pago_incluye_monto_en_el_mensaje(): void
    {
        $payload = (new PaymentReceiptNotification(new Payment(['monto' => 250.5])))->toWebPush($this->notifiable(true));

        $this->assertSame('Pago recibido', $payload['title']);
        $this->assertStringContainsString('$250.50', $payload['body']);
    }

    public function test_tipo_desconocido_cae_en_citas_y_canal_invalido_tambien(): void
    {
        $this->assertSame(['citas', 'notifications'], PushRouting::for('algo_nuevo'));
        $this->assertSame('citas', PushRouting::channel('inventado'));
        $this->assertSame('pagos', PushRouting::channel('pagos'));
    }
}
