<?php

namespace App\Notifications;

use App\Services\Loyalty\LoyaltyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Felicitación de cumpleaños con puntos de regalo (ver
 * LoyaltyService::awardBirthdayPoints), disparada por
 * SendBirthdayGreetingsCommand a partir de Client::fecha_nacimiento.
 */
class ClientBirthdayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('¡Feliz cumpleaños de parte de UrbanBlade!')
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Cumpleaños',
                'title' => '¡Feliz cumpleaños, '.$notifiable->name.'!',
                'intro' => 'Todo el equipo de UrbanBlade te desea un excelente día. Como regalo, agregamos **'.LoyaltyService::BIRTHDAY_POINTS.' puntos** a tu cuenta de lealtad.',
                'rows' => [
                    'Puntos de regalo' => (string) LoyaltyService::BIRTHDAY_POINTS,
                ],
                'ctaLabel' => 'Reservar una cita',
                'ctaUrl' => $this->dashboardUrl(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'client_birthday',
            'title' => '¡Feliz cumpleaños!',
            'message' => 'Te regalamos '.LoyaltyService::BIRTHDAY_POINTS.' puntos de lealtad por tu cumpleaños.',
            'puntos' => LoyaltyService::BIRTHDAY_POINTS,
            'url' => $this->dashboardUrl(),
        ];
    }

    private function dashboardUrl(): string
    {
        try {
            return route('dashboard');
        } catch (\Throwable $e) {
            return url('/');
        }
    }
}
