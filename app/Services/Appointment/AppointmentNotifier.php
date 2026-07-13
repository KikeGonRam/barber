<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Reparte las notificaciones de una cita a TODOS los roles implicados
 * (cliente, barbero, recepcion/admin) con contenido y accion propios de cada
 * uno. Nunca lanza: una falla de notificacion no debe romper la reserva.
 */
class AppointmentNotifier
{
    /**
     * Cita recien creada.
     */
    public function created(Appointment $appointment): void
    {
        $appointment->loadMissing(['client.user', 'barber.user', 'service']);
        $cliente = $appointment->client?->user?->name ?? 'Un cliente';
        $barbero = $appointment->barber?->user?->name ?? 'el barbero';
        $fecha = optional($appointment->fecha)->format('d/m/Y') ?? 'la fecha indicada';

        // Cliente
        $this->send($appointment->client?->user, $appointment,
            'Confirmacion de cita', 'Tu cita fue registrada',
            'Tu cita fue confirmada. Te esperamos el dia indicado.',
            'Ver mis citas', $this->route('client.appointments.index'));

        // Barbero
        $this->send($appointment->barber?->user, $appointment,
            'Nueva cita agendada', 'Tienes una nueva cita',
            "{$cliente} agendo una cita contigo para el {$fecha}.",
            'Ver mi agenda', $this->route('barber.agenda'));

        // Recepcion + Admin
        $this->sendStaff($appointment,
            'Nueva reserva', 'Nueva cita en el sistema',
            "{$cliente} reservo con {$barbero} para el {$fecha}.");

        $this->stamp($appointment, 'confirmation_sent_at');
    }

    /**
     * Cita cancelada. $origin describe de donde vino (web, app movil, etc.).
     */
    public function cancelled(Appointment $appointment, string $origin = ''): void
    {
        $appointment->loadMissing(['client.user', 'barber.user', 'service']);
        $cliente = $appointment->client?->user?->name ?? 'Un cliente';
        $barbero = $appointment->barber?->user?->name ?? 'el barbero';
        $suffix = $origin !== '' ? " ({$origin})" : '';

        // Cliente
        $this->send($appointment->client?->user, $appointment,
            'Cita cancelada', 'Tu cita fue cancelada',
            'Tu cita fue cancelada. Si deseas, puedes reagendar desde tu panel.',
            'Reagendar', $this->route('client.appointments.index'));

        // Barbero
        $this->send($appointment->barber?->user, $appointment,
            'Cita cancelada', 'Se cancelo una cita',
            "{$cliente} cancelo su cita contigo{$suffix}.",
            'Ver mi agenda', $this->route('barber.agenda'));

        // Recepcion + Admin
        $this->sendStaff($appointment,
            'Cita cancelada', 'Se cancelo una cita',
            "Se cancelo la cita de {$cliente} con {$barbero}{$suffix}.");

        $this->stamp($appointment, 'cancellation_notified_at');
    }

    /**
     * Cambio de estado relevante (completada, no_asistio, etc.). Notifica al
     * cliente y, en no_asistio, tambien informa al barbero.
     */
    public function statusChanged(Appointment $appointment, string $estado): void
    {
        $appointment->loadMissing(['client.user', 'barber.user', 'service']);

        $map = [
            'completada' => ['Cita completada', 'Gracias por tu visita', 'Tu cita fue completada. Esperamos verte pronto.'],
            'confirmada' => ['Cita confirmada', 'Tu cita fue confirmada', 'Tu cita quedo confirmada. Te esperamos.'],
            'en_proceso' => ['Tu cita esta en proceso', 'Estas siendo atendido', 'Tu servicio esta en proceso. Disfrutalo.'],
            'no_asistio' => ['Marcada como no asistio', 'No registramos tu asistencia', 'Tu cita fue marcada como no asistida. Contacta a recepcion si es un error.'],
        ];

        if (! isset($map[$estado])) {
            return; // estados sin notificacion al cliente
        }

        [$subject, $title, $message] = $map[$estado];

        $this->send($appointment->client?->user, $appointment,
            $subject, $title, $message,
            'Ver mis citas', $this->route('client.appointments.index'));
    }

    private function send(?User $user, Appointment $appointment, string $subject, string $title, string $message, string $actionLabel, ?string $actionUrl): void
    {
        if (! $user) {
            return;
        }

        try {
            $user->notify(new AppointmentNotification(
                appointment: $appointment,
                subject: $subject,
                title: $title,
                message: $message,
                actionLabel: $actionLabel,
                actionUrl: $actionUrl,
            ));
        } catch (\Throwable $e) {
            Log::warning('Fallo notificacion de cita', [
                'appointment_id' => $appointment->id,
                'user_id' => $user->id,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendStaff(Appointment $appointment, string $subject, string $title, string $message): void
    {
        try {
            $staff = User::whereRoleName(['recepcionista', 'administrador'])->get();

            if ($staff->isEmpty()) {
                return;
            }

            Notification::send($staff, new AppointmentNotification(
                appointment: $appointment,
                subject: $subject,
                title: $title,
                message: $message,
                actionLabel: 'Ver agenda',
                actionUrl: $this->route('appointments.index'),
            ));
        } catch (\Throwable $e) {
            Log::warning('Fallo notificacion de cita a staff', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function stamp(Appointment $appointment, string $column): void
    {
        try {
            $appointment->update([$column => now()]);
        } catch (\Throwable $e) {
            // no critico
        }
    }

    private function route(string $name): ?string
    {
        try {
            return route($name);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
