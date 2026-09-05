<?php

namespace App\Notifications;

use App\Models\RaffleResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de haber ganado el sorteo mensual. Reemplaza el reuso de
 * LoyaltyNotification (pensada para "subiste de nivel") que antes se
 * mandaba aquí con discount:100 — le decía al ganador que tenía 100% de
 * descuento PERMANENTE, nunca mencionaba "sorteo"/"premio" ni una fecha
 * límite para reclamarlo.
 */
class RaffleWinNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RaffleResult $prize) {}

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
            ->subject('¡Ganaste el sorteo mensual de UrbanBlade!')
            ->markdown('emails.message', [
                'accent' => '#e879f9',
                'badge' => 'Ganador',
                'title' => '¡Felicidades, '.$notifiable->name.'!',
                'intro' => 'Ganaste el sorteo mensual de clientes VIP/Leyenda. Tu premio: **'.$this->prize->premio.'**. Coméntaselo a recepción en tu próxima visita para reclamarlo.',
                'rows' => [
                    'Premio' => $this->prize->premio,
                    'Válido hasta' => optional($this->prize->vence_en)->format('d/m/Y'),
                ],
                'ctaLabel' => 'Reservar una cita',
                'ctaUrl' => $this->dashboardUrl(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'raffle_win',
            'title' => '¡Ganaste el sorteo!',
            'message' => 'Tu premio: '.$this->prize->premio.'. Válido hasta '.optional($this->prize->vence_en)->format('d/m/Y').'.',
            'premio' => $this->prize->premio,
            'vence_en' => optional($this->prize->vence_en)->toIso8601String(),
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
