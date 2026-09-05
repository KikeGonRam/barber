<?php

namespace App\Notifications;

use App\Services\Loyalty\LoyaltyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de baja de nivel de lealtad por inactividad (180+ dias sin cita
 * completada, ver LoyaltyService::applyInactivityLifecycle). El tono es
 * de invitacion a volver, no de castigo — el nivel se recupera con la
 * siguiente cita, no hay que "reganarlo" desde cero.
 */
class LoyaltyLevelDowngradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $previousLevel,
        public readonly string $newLevel,
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
        $label = LoyaltyService::LEVEL_LABELS[$this->newLevel] ?? strtoupper($this->newLevel);

        return (new MailMessage)
            ->subject('Te extrañamos en UrbanBlade')
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Nivel '.$label,
                'title' => 'Hace tiempo no te vemos, '.$notifiable->name,
                'intro' => 'Por un tiempo sin visitarnos, tu nivel de lealtad bajó a **'.$label.'**. La buena noticia: se recupera con tu próxima cita, no tienes que empezar de cero.',
                'rows' => [
                    'Nivel actual' => $label,
                    'Descuento actual' => LoyaltyService::discountPct($this->newLevel).'% en tus servicios',
                ],
                'ctaLabel' => 'Reservar una cita',
                'ctaUrl' => route('dashboard'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $label = LoyaltyService::LEVEL_LABELS[$this->newLevel] ?? strtoupper($this->newLevel);

        return [
            'type' => 'loyalty_level_downgraded',
            'level' => $this->newLevel,
            'previous_level' => $this->previousLevel,
            'title' => 'Te extrañamos',
            'message' => "Tu nivel bajó a {$label} por inactividad. Vuelve pronto para recuperarlo.",
        ];
    }
}
