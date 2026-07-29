<?php

namespace App\Notifications;

use App\Services\Loyalty\LoyaltyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoyaltyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $level,
        public readonly string $previousLevel,
        public readonly int $discount,
    ) {}

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
        $label = LoyaltyService::LEVEL_LABELS[$this->level] ?? strtoupper($this->level);

        return (new MailMessage)
            ->subject("¡Subiste de nivel! Ahora eres {$label}")
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Nivel '.$label,
                'title' => '¡Felicidades, '.$notifiable->name.'!',
                'intro' => "Alcanzaste el nivel **{$label}** en UrbanBlade. Aqui estan tus nuevos beneficios.",
                'rows' => [
                    'Nuevo nivel' => $label,
                    'Descuento' => $this->discount.'% en todos tus servicios',
                    'Beneficio' => 'Prioridad en reservas',
                ],
                'ctaLabel' => 'Ver mi progreso',
                'ctaUrl' => route('dashboard'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $label = LoyaltyService::LEVEL_LABELS[$this->level] ?? strtoupper($this->level);

        return [
            'type' => 'loyalty_level_up',
            'level' => $this->level,
            'previous_level' => $this->previousLevel,
            'title' => "¡Subiste a {$label}!",
            'message' => "Ahora tienes {$this->discount}% de descuento en tus próximos servicios.",
            'discount' => $this->discount,
        ];
    }
}
