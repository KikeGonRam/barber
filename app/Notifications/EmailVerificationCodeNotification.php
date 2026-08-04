<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Codigo de verificacion de registro (6 digitos, vence en 10 min), enviado
 * con la plantilla de marca en vez del enlace firmado default de Laravel.
 */
class EmailVerificationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu código de verificación — UrbanBlade')
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Verificación',
                'title' => 'Confirma tu correo',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => 'Usa este código para verificar tu cuenta. Vence en 10 minutos.',
                'rows' => [
                    'Código de verificación' => $this->code,
                ],
            ]);
    }
}
