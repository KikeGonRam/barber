<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentNotification;
use App\Services\Messaging\MessagingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Envía recordatorios de citas próximas por notificación (email/in-app) y por
 * mensajería directa (SMS/WhatsApp) según las preferencias del usuario: uno
 * a 24 horas antes y otro a 2 horas antes. Se ejecuta cada 10 minutos vía el
 * scheduler (Schedule::command('appointments:send-reminders')->everyTenMinutes()).
 */
class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Enviar recordatorios de citas (24h y 2h antes).';

    /**
     * Dispara los dos flujos de recordatorio (24h y 2h) en cada ejecución.
     */
    public function handle(MessagingService $messagingService): int
    {
        $now = Carbon::now();

        $this->send24hReminder($now, $messagingService);
        $this->send2hReminder($now, $messagingService);

        return self::SUCCESS;
    }

    /**
     * Recordatorio de 24h: notifica a todas las citas de mañana (sin ventana
     * horaria) que aún no tengan el flag reminder_24h_sent_at.
     */
    private function send24hReminder(Carbon $now, MessagingService $messagingService): void
    {
        // Se toman TODAS las citas de manana, sin filtrar por ventana horaria.
        // El flag reminder_24h_sent_at garantiza que cada cita se notifique una sola vez
        // (evita que corridas repetidas del scheduler dupliquen el aviso).
        $tomorrow = $now->copy()->addDay()->toDateString();

        Appointment::query()
            ->with(['client.user', 'service'])
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->whereNull('reminder_24h_sent_at')
            ->where('fecha', $tomorrow)
            ->chunkById(100, function ($appointments) use ($messagingService) {
                foreach ($appointments as $appointment) {
                    $user = $appointment->client?->user;

                    if (! $user) {
                        continue;
                    }

                    try {
                        $user->notify(new AppointmentNotification(
                            appointment: $appointment,
                            subject: 'Recordatorio de cita — mañana',
                            title: 'Tu cita es mañana',
                            message: 'Te recordamos que tienes una cita programada para mañana.',
                        ));

                        $this->dispatchDirectMessage($user, 'Recordatorio: tu cita es mañana.', $messagingService);
                    } catch (\Throwable $e) {
                        Log::warning('Fallo recordatorio 24h', [
                            'appointment_id' => $appointment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $appointment->update(['reminder_24h_sent_at' => now()]);
                }
            });
    }

    /**
     * Recordatorio de 2h: notifica a las citas de hoy cuya hora de inicio cae
     * dentro de una ventana de 90 a 150 minutos a partir de ahora.
     */
    private function send2hReminder(Carbon $now, MessagingService $messagingService): void
    {
        // Ventana: citas que inician entre 90 y 150 minutos desde ahora (el comando
        // corre cada 10 min, asi que la ventana de 60 min evita huecos sin cubrir).
        // La comparacion de strings funciona correctamente porque hora_inicio se
        // guarda en formato HH:MM:SS con ceros a la izquierda (orden lexicografico
        // coincide con orden cronologico) en MongoDB.
        $from = $now->copy()->addMinutes(90)->format('H:i:s');
        $to = $now->copy()->addMinutes(150)->format('H:i:s');
        $today = $now->toDateString();

        Appointment::query()
            ->with(['client.user', 'service'])
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->whereNull('reminder_2h_sent_at')
            ->where('fecha', $today)
            ->where('hora_inicio', '>=', $from)
            ->where('hora_inicio', '<=', $to)
            ->chunkById(100, function ($appointments) use ($messagingService) {
                foreach ($appointments as $appointment) {
                    $user = $appointment->client?->user;

                    if (! $user) {
                        continue;
                    }

                    try {
                        $user->notify(new AppointmentNotification(
                            appointment: $appointment,
                            subject: 'Recordatorio de cita — en 2 horas',
                            title: 'Tu cita es en 2 horas',
                            message: 'Te recordamos que tu cita inicia aproximadamente en 2 horas. ¡Nos vemos pronto!',
                        ));

                        $this->dispatchDirectMessage($user, 'Recordatorio: tu cita es en aproximadamente 2 horas.', $messagingService);
                    } catch (\Throwable $e) {
                        Log::warning('Fallo recordatorio 2h', [
                            'appointment_id' => $appointment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $appointment->update(['reminder_2h_sent_at' => now()]);
                }
            });
    }

    /**
     * Envía el recordatorio por SMS y/o WhatsApp según el teléfono disponible
     * y los canales de notificación que el usuario haya habilitado.
     */
    private function dispatchDirectMessage($user, string $message, MessagingService $messagingService): void
    {
        $phone = method_exists($user, 'clientPhone') ? $user->clientPhone() : null;

        if (! $phone) {
            return;
        }

        if (method_exists($user, 'wantsNotificationChannel') && $user->wantsNotificationChannel('sms')) {
            $messagingService->sendSms($phone, $message);
        }

        if (method_exists($user, 'wantsNotificationChannel') && $user->wantsNotificationChannel('whatsapp')) {
            $messagingService->sendWhatsapp($phone, $message);
        }
    }
}
