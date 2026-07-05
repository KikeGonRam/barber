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
            ->greeting('¡Felicidades, ' . $notifiable->name . '!')
            ->line("Has alcanzado el nivel **{$label}** en UrbanBlade.")
            ->line("A partir de ahora tienes un **{$this->discount}% de descuento** en todos tus servicios.")
            ->action('Ver mi progreso', route('dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        $label = LoyaltyService::LEVEL_LABELS[$this->level] ?? strtoupper($this->level);

        return [
            'type'           => 'loyalty_level_up',
            'level'          => $this->level,
            'previous_level' => $this->previousLevel,
            'title'          => "¡Subiste a {$label}!",
            'message'        => "Ahora tienes {$this->discount}% de descuento en tus próximos servicios.",
            'discount'       => $this->discount,
        ];
    }
}
