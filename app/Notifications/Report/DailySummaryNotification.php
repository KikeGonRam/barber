<?php

namespace App\Notifications\Report;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Resumen de cierre de jornada para administracion.
 *
 * @param  array<string, string>  $stats  filas etiqueta => valor
 */
class DailySummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly array $stats,
        public readonly string $dateLabel,
    ) {}

    /**
     * Database siempre; correo opcional segun preferencia del admin.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Correo con la plantilla compartida; $stats ya viene formateado desde
     * el comando que dispara esta notificacion.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Resumen del dia — '.$this->dateLabel)
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Cierre de jornada',
                'title' => 'Resumen del dia',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => $this->dateLabel.' · el pulso de tu negocio en un vistazo.',
                'rows' => $this->stats,
                'ctaLabel' => 'Ver reportes',
                'ctaUrl' => $this->reportsUrl(),
            ]);
    }

    /**
     * Payload para el canal database (centro de notificaciones in-app).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'daily_summary',
            'title' => 'Resumen del dia',
            'message' => 'Cierre de jornada de '.$this->dateLabel,
            'stats' => $this->stats,
        ];
    }

    /**
     * Intenta varias rutas de reportes segun lo que exista registrado en la
     * app; si ninguna existe, cae a la raiz en vez de lanzar excepcion.
     */
    private function reportsUrl(): string
    {
        foreach (['reports.index', 'dashboard'] as $name) {
            try {
                return route($name);
            } catch (\Throwable $e) {
                // siguiente
            }
        }

        return url('/');
    }
}
