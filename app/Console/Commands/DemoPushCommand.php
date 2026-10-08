<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PushSubscription;
use App\Models\RaffleResult;
use App\Models\User;
use App\Notifications\Appointment\AppointmentNotification;
use App\Notifications\Campaign\PromotionNotification;
use App\Notifications\Channels\FcmPushChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Inventory\InventoryLowStockNotification;
use App\Notifications\Loyalty\ClientBirthdayNotification;
use App\Notifications\Loyalty\LoyaltyNotification;
use App\Notifications\Loyalty\RaffleWinNotification;
use App\Notifications\Order\OrderDeliveredNotification;
use App\Notifications\Payment\PaymentReceiptNotification;
use App\Services\Push\FcmPushService;
use App\Services\Push\WebPushService;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Manda un aviso de ejemplo SOLO por push (navegador y/o teléfono) a un usuario, sin guardar nada ni
 * mandar correo, y diagnostica qué falta si no llega. Pensado para ensayos y demos.
 *
 *   php artisan urbanblade:demo-push correo@dominio.com --tipo=pago
 */
class DemoPushCommand extends Command
{
    protected $signature = 'urbanblade:demo-push {email : Correo del usuario que recibe el aviso}
        {--tipo=pago : cita, pago, pedido, nivel, sorteo, cumpleanos, promocion, inventario}';

    protected $description = 'Envía un aviso de ejemplo solo por push (web y Android) y diagnostica la configuración';

    public function handle(WebPushService $web, FcmPushService $fcm): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No existe un usuario con ese correo.');

            return self::FAILURE;
        }

        $subs = PushSubscription::where('user_id', (string) $user->id)->count();
        $hasFcm = filled($user->getAttribute('fcm_token'));

        $this->table(['Diagnóstico', 'Estado'], [
            ['Claves VAPID (web) configuradas', $web->isConfigured() ? 'sí' : 'NO - falta VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY'],
            ['Suscripciones web de este usuario', $subs > 0 ? (string) $subs : '0 - abre la web, inicia sesión y activa las notificaciones'],
            ['Credenciales de Firebase (Android)', $fcm->isConfigured() ? 'sí' : 'NO - falta FIREBASE_PROJECT_ID / FIREBASE_CREDENTIALS_PATH'],
            ['Token FCM del teléfono', $hasFcm ? 'sí' : 'no - inicia sesión en la app Android'],
            ['Canal push activado en preferencias', $user->wantsNotificationChannel('push') ? 'sí' : 'no (este comando lo ignora; los avisos reales no saldrán)'],
        ]);

        $notification = $this->notification((string) $this->option('tipo'));
        if (! $notification) {
            $this->error('Tipo desconocido. Usa: cita, pago, pedido, nivel, sorteo, cumpleanos, promocion, inventario.');

            return self::FAILURE;
        }

        // sendNow con canales explícitos: solo push, en el momento, sin cola ni bandeja ni correo.
        NotificationFacade::sendNow($user, $notification, [WebPushChannel::class, FcmPushChannel::class]);
        // Todas las notificaciones de esta lista usan PushesToDevices (o AppointmentNotification), que define toWebPush().
        $payload = method_exists($notification, 'toWebPush') ? $notification->toWebPush($user) : [];
        $this->info('Enviado: «'.($payload['title'] ?? '—').'» (canal '.($payload['channel'] ?? '—').', abre '.($payload['route'] ?? '—').').');

        return self::SUCCESS;
    }

    private function notification(string $tipo): ?Notification
    {
        return match ($tipo) {
            'cita' => new AppointmentNotification(new Appointment, 'Recordatorio', 'Recordatorio de tu cita', 'Tu cita es mañana a las 11:30. Te esperamos.'),
            'pago' => new PaymentReceiptNotification(new Payment(['monto' => 250, 'propina' => 0, 'metodo_pago' => 'tarjeta'])),
            'pedido' => new OrderDeliveredNotification(new Order(['folio' => 'PED-DEMO', 'total' => 300])),
            'nivel' => new LoyaltyNotification('oro', 'plata', 10),
            'sorteo' => new RaffleWinNotification(new RaffleResult(['premio' => 'Corte gratis'])),
            'cumpleanos' => new ClientBirthdayNotification,
            'promocion' => new PromotionNotification('2x1 en cortes este viernes', 'Trae a un amigo y paga solo un corte.'),
            'inventario' => new InventoryLowStockNotification([['nombre' => 'Cera', 'stock_actual' => 1]]),
            default => null,
        };
    }
}
