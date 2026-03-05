<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentNotification;
use App\Services\MessagingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
        $target = $now->copy()->addDay();

        Appointment::query()
            ->with(['client.user', 'service'])
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->whereNull('reminder_24h_sent_at')
            ->whereDate('fecha', $target->toDateString())
            ->whereTime('hora_inicio', '>=', $target->copy()->startOfHour()->format('H:i:s'))
            ->whereTime('hora_inicio', '<', $target->copy()->startOfHour()->addHour()->format('H:i:s'))
            ->chunkById(100, function ($appointments) use ($messagingService) {
                foreach ($appointments as $appointment) {
                    $user = $appointment->client?->user;

                    if (! $user) {
                        continue;
                    }

                    $user->notify(new AppointmentNotification(
                        appointment: $appointment,
                        subject: 'Recordatorio de cita (24h)',
                        title: 'Tu cita es mañana',
                        message: 'Te recordamos que tienes una cita programada para mañana.',
                    ));

                    $this->dispatchDirectMessage($user, 'Recordatorio: tu cita es mañana.', $messagingService);

                    $appointment->update(['reminder_24h_sent_at' => now()]);
                }
            });
    }

    private function send2hReminder(Carbon $now, MessagingService $messagingService): void
    {
        $target = $now->copy()->addHours(2);

        Appointment::query()
            ->with(['client.user', 'service'])
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->whereNull('reminder_2h_sent_at')
            ->whereDate('fecha', $target->toDateString())
            ->whereTime('hora_inicio', '>=', $target->copy()->startOfHour()->format('H:i:s'))
            ->whereTime('hora_inicio', '<', $target->copy()->startOfHour()->addHour()->format('H:i:s'))
            ->chunkById(100, function ($appointments) use ($messagingService) {
                foreach ($appointments as $appointment) {
                    $user = $appointment->client?->user;

                    if (! $user) {
                        continue;
                    }

                    $user->notify(new AppointmentNotification(
                        appointment: $appointment,
                        subject: 'Recordatorio de cita (2h)',
                        title: 'Tu cita es en 2 horas',
                        message: 'Te recordamos que tu cita inicia aproximadamente en 2 horas.',
                    ));

                    $this->dispatchDirectMessage($user, 'Recordatorio: tu cita es en aproximadamente 2 horas.', $messagingService);

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
