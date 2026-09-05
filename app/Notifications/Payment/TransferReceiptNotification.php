<?php

namespace App\Notifications\Payment;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa al cliente sobre el estado de su comprobante de transferencia:
 * recibido (en revision), aprobado (ver PaymentReceiptNotification en su
 * lugar) o rechazado (con motivo, para que vuelva a subir uno correcto).
 */
class TransferReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payment $payment, public readonly string $status, public readonly ?string $motivo = null) {}

    /**
     * Igual patron que AppointmentNotification: sin preferencias explicitas
     * cae a 'database' para no perder el aviso.
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('in_app')) {
            $channels[] = 'database';
        }

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return $channels ?: ['database'];
    }

    /**
     * Elige plantilla segun $status: rechazo (con motivo, en rojo) o
     * recibido/en revision (en azul, informativo).
     */
    public function toMail(object $notifiable): MailMessage
    {
        $servicio = $this->payment->appointment?->service?->nombre ?? 'tu servicio';
        $url = route('client.appointments.index');

        if ($this->status === 'rechazado') {
            return (new MailMessage)
                ->subject('Tu comprobante necesita revisión — '.$servicio)
                ->markdown('emails.message', [
                    'accent' => '#ef4444',
                    'badge' => 'Rechazado',
                    'title' => 'Tu comprobante fue rechazado',
                    'greeting' => 'Hola '.$notifiable->name.',',
                    'intro' => $this->motivo
                        ? "No pudimos validar tu comprobante: {$this->motivo}. Por favor sube uno nuevo."
                        : 'No pudimos validar tu comprobante. Por favor sube uno nuevo.',
                    'ctaLabel' => 'Subir comprobante',
                    'ctaUrl' => $url,
                ]);
        }

        return (new MailMessage)
            ->subject('Recibimos tu comprobante — '.$servicio)
            ->markdown('emails.message', [
                'accent' => '#5b8def',
                'badge' => 'En revisión',
                'title' => 'Tu comprobante está en revisión',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => 'Recibimos tu comprobante de transferencia y lo estamos revisando. Te avisaremos en cuanto se confirme.',
                'ctaLabel' => 'Ver mis citas',
                'ctaUrl' => $url,
            ]);
    }

    /**
     * Payload para el canal database (centro de notificaciones in-app).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'transfer_receipt',
            'payment_id' => $this->payment->id,
            'appointment_id' => $this->payment->appointment_id,
            'status' => $this->status,
            'message' => $this->status === 'rechazado'
                ? 'Tu comprobante fue rechazado.'.($this->motivo ? ' Motivo: '.$this->motivo : '').' Sube uno nuevo.'
                : 'Recibimos tu comprobante, está en revisión.',
        ];
    }
}
