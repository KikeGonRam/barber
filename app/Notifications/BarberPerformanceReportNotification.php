<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reporte mensual de desempeño de barberos para administración: reconoce al
 * mejor mes y avisa de caídas fuertes (BarberPerformanceService::DROP_THRESHOLD_PCT)
 * para que alguien decida si hay que hablar con el barbero, revisar su horario,
 * etc. — no llega directo al barbero, solo a quien gestiona el equipo.
 *
 * @param  ?array{barber_id:string,nombre:string,citas:int}  $topPerformer
 * @param  array<int, array{barber_id:string,nombre:string,citas_mes:int,citas_mes_anterior:int,caida_pct:int}>  $underperformers
 */
class BarberPerformanceReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $closedMonth,
        public readonly ?array $topPerformer,
        public readonly array $underperformers,
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
        $rows = [];

        if ($this->topPerformer) {
            $rows['Mejor mes'] = "{$this->topPerformer['nombre']} ({$this->topPerformer['citas']} citas completadas)";
        }

        foreach ($this->underperformers as $u) {
            $rows["Caída: {$u['nombre']}"] = "{$u['citas_mes']} citas (vs {$u['citas_mes_anterior']} el mes anterior, -{$u['caida_pct']}%)";
        }

        return (new MailMessage)
            ->subject("Desempeño de barberos: {$this->closedMonth}")
            ->markdown('emails.message', [
                'accent' => count($this->underperformers) > 0 ? '#ef4444' : '#d4af37',
                'badge' => $this->closedMonth,
                'title' => 'Reporte mensual de desempeño',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => count($this->underperformers) > 0
                    ? 'Este mes hay barberos con una caída fuerte de citas que vale la pena revisar, junto con el reconocimiento al mejor mes.'
                    : 'Ningún barbero tuvo una caída fuerte este mes. Aquí el reconocimiento al mejor mes.',
                'rows' => $rows,
                'ctaLabel' => 'Ver barberos',
                'ctaUrl' => $this->barbersUrl(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'barber_performance_report',
            'title' => "Desempeño de barberos: {$this->closedMonth}",
            'message' => count($this->underperformers) > 0
                ? count($this->underperformers).' barbero(s) con caída fuerte este mes.'
                : 'Sin caídas fuertes este mes.',
            'closed_month' => $this->closedMonth,
            'top_performer' => $this->topPerformer,
            'underperformers' => $this->underperformers,
            'url' => $this->barbersUrl(),
        ];
    }

    private function barbersUrl(): string
    {
        try {
            return route('barbers.index');
        } catch (\Throwable $e) {
            return url('/');
        }
    }
}
