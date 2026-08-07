<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Notifications\ReviewRequestNotification;
use App\Notifications\ServiceOverrunNotification;
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

        // Cliente (con invite de calendario)
        $this->send($appointment->client?->user, $appointment,
            'Confirmacion de cita', 'Tu cita esta reservada',
            'Te esperamos. Aqui estan los detalles de tu visita.',
            'Ver mi cita', $this->route('client.appointments.index'),
            '#10b981', 'Confirmada', true);

        // Barbero
        $this->send($appointment->barber?->user, $appointment,
            'Nueva cita agendada', 'Tienes una nueva cita',
            "{$cliente} agendo una cita contigo para el {$fecha}.",
            'Ver mi agenda', $this->route('barber.agenda'),
            '#5b8def', 'Nueva reserva');

        // Recepcion + Admin
        $this->sendStaff($appointment,
            'Nueva reserva', 'Nueva cita en el sistema',
            "{$cliente} reservo con {$barbero} para el {$fecha}.",
            '#94a3b8', 'Reserva online');

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
            'Reagendar', $this->route('client.appointments.index'),
            '#ef4444', 'Cancelada');

        // Barbero
        $this->send($appointment->barber?->user, $appointment,
            'Cita cancelada', 'Se cancelo una cita',
            "{$cliente} cancelo su cita contigo{$suffix}.",
            'Ver mi agenda', $this->route('barber.agenda'),
            '#ef4444', 'Cancelada');

        // Recepcion + Admin
        $this->sendStaff($appointment,
            'Cita cancelada', 'Se cancelo una cita',
            "Se cancelo la cita de {$cliente} con {$barbero}{$suffix}.",
            '#ef4444', 'Cancelada');

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
            'completada' => ['Cita completada', 'Gracias por tu visita', 'Tu cita fue completada. Esperamos verte pronto.', '#d4af37', 'Completada'],
            'confirmada' => ['Cita confirmada', 'Tu cita fue confirmada', 'Tu cita quedo confirmada. Te esperamos.', '#10b981', 'Confirmada'],
            'en_proceso' => ['Tu cita esta en proceso', 'Estas siendo atendido', 'Tu servicio esta en proceso. Disfrutalo.', '#5b8def', 'En proceso'],
            'no_asistio' => ['Marcada como no asistio', 'No registramos tu asistencia', 'Tu cita fue marcada como no asistida. Contacta a recepcion si es un error.', '#f59e0b', 'No asistio'],
        ];

        if (! isset($map[$estado])) {
            return; // estados sin notificacion al cliente
        }

        [$subject, $title, $message, $accent, $badge] = $map[$estado];

        $this->send($appointment->client?->user, $appointment,
            $subject, $title, $message,
            'Ver mis citas', $this->route('client.appointments.index'),
            $accent, $badge, $estado === 'confirmada');

        // Tras completar, pide resena al cliente (con retraso para no
        // colisionar con el correo de "completada").
        if ($estado === 'completada' && ($client = $appointment->client?->user)) {
            try {
                $client->notify((new ReviewRequestNotification($appointment))->delay(now()->addHours(2)));
            } catch (\Throwable $e) {
                Log::warning('Fallo solicitud de resena', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Servicio "en_proceso" que ya supero su tiempo estimado. Avisa solo al
     * barbero asignado, canal in-app unicamente (ver ServiceOverrunNotification).
     * Se repite cada 5 min via NotifyServiceOverrunCommand mientras el
     * barbero no lo marque como completado.
     */
    public function serviceOverrun(Appointment $appointment): void
    {
        $appointment->loadMissing(['client.user', 'barber.user', 'service']);

        $barber = $appointment->barber?->user;
        if (! $barber) {
            return;
        }

        try {
            $barber->notify(new ServiceOverrunNotification($appointment));
        } catch (\Throwable $e) {
            Log::warning('Fallo notificacion de servicio con tiempo excedido', [
                'appointment_id' => $appointment->id,
                'barber_user_id' => $barber->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * El cliente subio un comprobante de transferencia pendiente de revision.
     * Avisa a recepcion/admin para que lo revisen.
     */
    public function transferReceiptUploaded(Appointment $appointment, \App\Models\Payment $payment): void
    {
        $cliente = $appointment->client?->user?->name ?? 'Un cliente';
        $servicio = $appointment->service?->nombre ?? 'un servicio';

        $this->sendStaff($appointment,
            'Comprobante por revisar', 'Nuevo comprobante de transferencia',
            "{$cliente} subio un comprobante para {$servicio}. Revisalo antes de aprobarlo.",
            '#5b8def', 'Por revisar');
    }

    /**
     * Envia una notificacion a un solo usuario. Atrapa cualquier excepcion
     * (mail/canal caido, etc.) y solo deja log: nunca debe tumbar el flujo
     * de la cita que la origino.
     */
    private function send(?User $user, Appointment $appointment, string $subject, string $title, string $message, string $actionLabel, ?string $actionUrl, string $accent = '#d4af37', ?string $badge = null, bool $attachCalendar = false): void
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
                accent: $accent,
                badge: $badge,
                attachCalendar: $attachCalendar,
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

    /**
     * Envia la misma notificacion a todo el staff (recepcion + admin) de una
     * sola vez via Notification::send (batch), no una a una.
     */
    private function sendStaff(Appointment $appointment, string $subject, string $title, string $message, string $accent = '#94a3b8', ?string $badge = null): void
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
                accent: $accent,
                badge: $badge,
            ));
        } catch (\Throwable $e) {
            Log::warning('Fallo notificacion de cita a staff', [
                'appointment_id' => $appointment->id,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Marca en la cita la hora en que se envio cierta notificacion (columna
     * de auditoria). Silencioso ante fallos: no es critico para el negocio.
     */
    private function stamp(Appointment $appointment, string $column): void
    {
        try {
            $appointment->update([$column => now()]);
        } catch (\Throwable $e) {
            // no critico
        }
    }

    /**
     * Resuelve una URL de ruta nombrada; devuelve null si la ruta no existe
     * (evita romper la notificacion por un nombre de ruta invalido).
     */
    private function route(string $name): ?string
    {
        try {
            return route($name);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
