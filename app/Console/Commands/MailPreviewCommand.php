<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\MembershipInvoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RaffleResult;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Appointment\AppointmentNotification;
use App\Notifications\Auth\EmailVerificationCodeNotification;
use App\Notifications\Auth\WelcomeNotification;
use App\Notifications\Barber\BarberPerformanceReportNotification;
use App\Notifications\Barber\BarberReviewFlaggedNotification;
use App\Notifications\Barber\ReviewRequestNotification;
use App\Notifications\Campaign\PromotionNotification;
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
use App\Notifications\Report\DailySummaryNotification;
use App\Services\Mail\ShopBranding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Vista previa de todos los correos (HT-25 / T073). Arma cada notificación con modelos en memoria (no guarda ni
 * envía nada), renderiza su HTML real con la plantilla emails.message y lo escribe en una carpeta con un índice,
 * para revisar el diseño de todos los tipos de un vistazo después de cualquier cambio.
 *
 *   php artisan urbanblade:mail-preview            → storage/app/mail-previews/index.html
 */
class MailPreviewCommand extends Command
{
    protected $signature = 'urbanblade:mail-preview {--dir= : Carpeta de salida (por defecto storage/app/mail-previews)}';

    protected $description = 'Genera la vista previa en HTML de todos los correos del sistema con datos de ejemplo';

    public function handle(): int
    {
        $dir = $this->option('dir') ?: storage_path('app/mail-previews');
        File::ensureDirectoryExists($dir);

        $cliente = new User(['name' => 'Luis Prueba', 'email' => 'cliente@urbanblade.test']);
        $logo = ShopBranding::logoDataUri();
        $fallos = 0;
        $indice = [];

        foreach ($this->cases() as $slug => [$titulo, $notification]) {
            try {
                $mail = $notification->toMail($cliente);
                // El logo va incrustado como «cid:» al enviar; en la vista previa se usa la misma imagen en línea.
                $html = str_replace('cid:'.ShopBranding::LOGO_CID, $logo, (string) $mail->render());
                File::put($dir.DIRECTORY_SEPARATOR.$slug.'.html', $html);
                $indice[] = ['slug' => $slug, 'titulo' => $titulo, 'asunto' => (string) $mail->subject];
                $this->line("  <info>✓</info> {$titulo}");
            } catch (Throwable $e) {
                $fallos++;
                $this->line("  <error>✗</error> {$titulo}: ".Str::limit($e->getMessage(), 160));
            }
        }

        File::put($dir.DIRECTORY_SEPARATOR.'index.html', $this->index($indice));
        $this->newLine();
        $this->info(count($indice).' correos generados en '.$dir.DIRECTORY_SEPARATOR.'index.html');

        return $fallos === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Un caso por tipo de correo, con datos de ejemplo verosímiles (nunca datos reales).
     *
     * @return array<string, array{0: string, 1: object}>
     */
    private function cases(): array
    {
        $cita = $this->appointment();
        $pago = (new Payment(['monto' => 250, 'propina' => 20, 'metodo_pago' => 'tarjeta', 'estado' => Payment::ESTADO_VERIFICADO]))
            ->setRelation('appointment', $cita);
        $pedido = new Order([
            'folio' => 'PED-0042',
            'total' => 360,
            'items' => [
                ['cantidad' => 1, 'nombre' => 'Pomada mate', 'precio' => 220, 'subtotal' => 220],
                ['cantidad' => 2, 'nombre' => 'Cera ligera', 'precio' => 70, 'subtotal' => 140],
            ],
        ]);

        return [
            'cita-confirmada' => ['Cita confirmada (con calendario)', new AppointmentNotification(
                $cita, 'Tu cita está confirmada', 'Tu cita está confirmada',
                'Tu barbero aprobó la cita. Te esperamos 10 minutos antes.',
                accent: '#22c55e', badge: 'Cita confirmada', attachCalendar: true,
            )],
            'cita-recordatorio' => ['Recordatorio de cita', new AppointmentNotification(
                $cita, 'Recordatorio de tu cita', 'Tu cita es mañana', 'Te esperamos mañana. Si no puedes venir, cancela desde la app.',
                badge: 'Recordatorio',
            )],
            'codigo-verificacion' => ['Código de verificación', new EmailVerificationCodeNotification('482915')],
            'bienvenida' => ['Bienvenida', new WelcomeNotification],
            'comprobante-pago' => ['Comprobante de pago', new PaymentReceiptNotification($pago)],
            'transferencia-revision' => ['Transferencia en revisión', new TransferReceiptNotification($pago, 'pendiente_verificacion')],
            'transferencia-rechazada' => ['Transferencia rechazada', new TransferReceiptNotification($pago, 'rechazado', 'El monto del comprobante no coincide con el de la cita.')],
            'resena-solicitud' => ['Pedir reseña', new ReviewRequestNotification($cita)],
            'resena-baja' => ['Reseña baja (staff)', new BarberReviewFlaggedNotification('Mario Pérez', 'Luis Prueba', 2, 'Tuve que esperar 20 minutos.')],
            'promocion' => ['Promoción', new PromotionNotification(
                '2x1 en arreglo de barba este viernes', 'Solo este viernes: trae a un amigo y el segundo arreglo de barba es gratis.',
                'Reservar ahora', ShopBranding::frontendUrl('/reservar'),
            )],
            'inventario-bajo' => ['Inventario bajo (staff)', new InventoryLowStockNotification([
                ['nombre' => 'Pomada mate', 'stock_actual' => 2, 'stock_minimo' => 5],
                ['nombre' => 'Navajas desechables', 'stock_actual' => 0, 'stock_minimo' => 20],
            ])],
            'cumpleanos' => ['Cumpleaños', new ClientBirthdayNotification],
            'nivel-sube' => ['Sube de nivel', new LoyaltyNotification('oro', 'plata', 10)],
            'nivel-baja' => ['Baja de nivel', new LoyaltyLevelDowngradedNotification('oro', 'plata')],
            'puntos-caducados' => ['Puntos caducados', new LoyaltyPointsExpiredNotification(120)],
            'sorteo-ganador' => ['Ganador del sorteo', new RaffleWinNotification(new RaffleResult(['premio' => 'Corte clásico gratis', 'vence_en' => now()->addDays(30)]))],
            'membresia-factura' => ['Factura de membresía', new MembershipInvoiceNotification(new MembershipInvoice(['monto' => 499, 'pagado_en' => now()]), 'Plan Oro')],
            'pedido-entregado' => ['Pedido entregado', new OrderDeliveredNotification($pedido)],
            'pedido-cancelado' => ['Pedido cancelado', new OrderExpiredNotification($pedido)],
            'desempeno-barberos' => ['Desempeño de barberos (staff)', new BarberPerformanceReportNotification(
                'octubre 2026',
                ['nombre' => 'Mario Pérez', 'citas' => 86],
                [['nombre' => 'Diego Ruiz', 'citas_mes' => 31, 'citas_mes_anterior' => 48, 'caida_pct' => 35]],
            )],
            'resumen-diario' => ['Resumen del día (staff)', new DailySummaryNotification([
                'Citas atendidas' => '14', 'No asistieron' => '1', 'Clientes nuevos' => '3', 'Ingresos' => '$3,430.00',
            ], 'sábado 22 de noviembre')],
        ];
    }

    /** Cita de ejemplo con sus relaciones ya cargadas: renderizar no consulta la base de datos. */
    private function appointment(): Appointment
    {
        $barbero = (new Barber)->setRelation('user', new User(['name' => 'Mario Pérez']));

        return (new Appointment([
            'fecha' => now()->addDays(5)->startOfDay(),
            'hora_inicio' => '16:00',
            'hora_fin' => '16:45',
            'estado' => 'confirmada',
        ]))
            ->setRelation('service', new Service(['nombre' => 'Corte clásico', 'duracion_min' => 45]))
            ->setRelation('barber', $barbero);
    }

    /** @param  list<array{slug: string, titulo: string, asunto: string}>  $casos */
    private function index(array $casos): string
    {
        $filas = collect($casos)->map(fn (array $c) => sprintf(
            '<li><a href="%s.html" target="vista">%s</a><small>%s</small></li>',
            e($c['slug']), e($c['titulo']), e($c['asunto']),
        ))->implode('');

        return <<<HTML
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Correos de UrbanBlade</title>
<style>body{margin:0;display:flex;height:100vh;font-family:system-ui,sans-serif;background:#0b0b0b;color:#eee}
nav{width:300px;overflow:auto;border-right:1px solid #2a2a2a;padding:16px}h1{font-size:15px;color:#d4af37;margin:0 0 12px}
ul{list-style:none;padding:0;margin:0}li{margin:0 0 10px}a{color:#f6f0e2;text-decoration:none;font-weight:600}a:hover{color:#d4af37}
small{display:block;color:#8c867a;font-size:12px;margin-top:2px}iframe{flex:1;border:0;background:#0b0b0b}</style></head>
<body><nav><h1>Correos de UrbanBlade</h1><ul>{$filas}</ul></nav><iframe name="vista" title="Vista previa del correo"></iframe></body></html>
HTML;
    }
}
