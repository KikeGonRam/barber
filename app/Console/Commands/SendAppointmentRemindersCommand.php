<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentNotification;
use App\Services\Messaging\MessagingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Enviar recordatorios de citas (24h y 2h antes).';

    public function handle(MessagingService $messagingService): int
    {
        $now = Carbon::now();

        $this->send24hReminder($now, $messagingService);
        $this->send2hReminder($now, $messagingService);

        return self::SUCCESS;
    }

    private function send24hReminder(Carbon $now, MessagingService $messagingService): void
    {
        // Get ALL appointments for tomorrow — no time-window filtering.
        // The sent_at flag ensures each appointment only gets notified once.
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

    private function send2hReminder(Carbon $now, MessagingService $messagingService): void
    {
        // Window: appointments starting between 90 and 150 minutes from now.
        // String comparison works correctly for zero-padded HH:MM:SS in MongoDB.
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
