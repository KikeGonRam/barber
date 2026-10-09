<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Models\NoShowFee;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Appointment\AppointmentNotification;
use App\Notifications\Appointment\ServiceEndingNotification;
use App\Notifications\Appointment\ServiceOverrunNotification;
use App\Notifications\Barber\ReviewRequestNotification;
use Carbon\Carbon;
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

        $clientUser = $appointment->client?->user;
        $barberUser = $appointment->barber?->user;

        // Una cita nace «pendiente»: el barbero la aprueba y solo entonces queda confirmada (statusChanged()).
        // Decirle al cliente «confirmada» antes de eso era engañoso.
        if ($appointment->estado === 'pendiente') {
            $this->send($clientUser, $appointment,
                'Recibimos tu solicitud de cita', 'Tu cita espera aprobación',
                'Tu barbero revisará tu solicitud y te avisaremos en cuanto la confirme. Hasta entonces no necesitas hacer nada más.',
                'Ver mi cita', $this->frontendUrl('/my/appointments'),
                '#f59e0b', 'Pendiente');

            $this->send($barberUser, $appointment,
                'Cita por aprobar', 'Tienes una cita por aprobar',
                "{$cliente} solicitó una cita contigo para el {$fecha}. Apruébala o recházala desde tu agenda.",
                'Revisar en mi agenda', $this->frontendUrl('/barber/agenda'),
                '#f59e0b', 'Por aprobar');
        } else {
            // Cliente (con invite de calendario)
            $this->send($clientUser, $appointment,
                'Confirmación de cita', 'Tu cita está reservada',
                'Te esperamos. Aquí están los detalles de tu visita.',
                'Ver mi cita', $this->frontendUrl('/my/appointments'),
                '#10b981', 'Confirmada', true);

            // Barbero
            $this->send($barberUser, $appointment,
                'Nueva cita agendada', 'Tienes una nueva cita',
                "{$cliente} agendó una cita contigo para el {$fecha}.",
                'Ver mi agenda', $this->frontendUrl('/barber/agenda'),
                '#5b8def', 'Nueva reserva');
        }

        // Recepcion + Admin
        $this->sendStaff($appointment,
            'Nueva reserva', 'Nueva cita en el sistema',
            "{$cliente} reservó con {$barbero} para el {$fecha}.",
            '#94a3b8', 'Reserva online');

        $this->stamp($appointment, 'confirmation_sent_at');
    }

    /**
     * Cita cancelada. $origin describe de donde vino (web, app movil, etc.). $byClient dice si la
     * canceló el propio cliente (el barbero lee «X canceló su cita») o el negocio desde la agenda
     * (el barbero lee «se canceló tu cita con X»).
     *
     * Toda ruta que cancele una cita debe llamar a este método: estados distintos de `cancelada`
     * pasan por statusChanged(), que a propósito no avisa de las cancelaciones.
     */
    public function cancelled(Appointment $appointment, string $origin = '', bool $byClient = true): void
    {
        $appointment->loadMissing(['client.user', 'barber.user', 'service']);
        $cliente = $appointment->client?->user?->name ?? 'Un cliente';
        $barbero = $appointment->barber?->user?->name ?? 'el barbero';
        $suffix = $origin !== '' ? " ({$origin})" : '';

        // Cliente
        $this->send($appointment->client?->user, $appointment,
            'Cita cancelada', 'Tu cita fue cancelada',
            'Tu cita fue cancelada. Si deseas, puedes reagendar desde tu panel.',
            'Reagendar', $this->frontendUrl('/my/appointments'),
            '#ef4444', 'Cancelada');

        // Barbero
        $this->send($appointment->barber?->user, $appointment,
            'Cita cancelada', 'Se canceló una cita',
            $byClient ? "{$cliente} canceló su cita contigo{$suffix}." : "Se canceló tu cita con {$cliente}{$suffix}.",
            'Ver mi agenda', $this->frontendUrl('/barber/agenda'),
            '#ef4444', 'Cancelada');

        // Recepcion + Admin
        $this->sendStaff($appointment,
            'Cita cancelada', 'Se canceló una cita',
            "Se canceló la cita de {$cliente} con {$barbero}{$suffix}.",
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
            'confirmada' => ['Cita confirmada', 'Tu cita fue confirmada', 'Tu cita quedó confirmada. Te esperamos.', '#10b981', 'Confirmada'],
            'en_proceso' => ['Tu cita está en proceso', 'Estás siendo atendido', 'Tu servicio está en proceso. Disfrútalo.', '#5b8def', 'En proceso'],
            'no_asistio' => ['Marcada como no asistió', 'No registramos tu asistencia', 'Tu cita fue marcada como no asistida. Contacta a recepción si es un error.', '#f59e0b', 'No asistió'],
        ];

        if (! isset($map[$estado])) {
            return; // estados sin notificacion al cliente
        }

        [$subject, $title, $message, $accent, $badge] = $map[$estado];

        $this->send($appointment->client?->user, $appointment,
            $subject, $title, $message,
            'Ver mis citas', $this->frontendUrl('/my/appointments'),
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
     * Servicio en curso a punto de terminar (NotifyServiceEndingCommand): avisa al barbero para que lo termine ya o
     * agregue tiempo antes de pasarse. Bandeja + push (ver ServiceEndingNotification).
     */
    public function serviceEnding(Appointment $appointment, int $minutesLeft): void
    {
        $appointment->loadMissing(['client.user', 'barber.user', 'service']);

        $barber = $appointment->barber?->getRelationValue('user');
        if (! $barber instanceof User) {
            return;
        }

        try {
            $barber->notify(new ServiceEndingNotification($appointment, $minutesLeft));
        } catch (\Throwable $e) {
            Log::warning('Fallo aviso de servicio por terminar', [
                'appointment_id' => (string) $appointment->id,
                'barber_user_id' => (string) $barber->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** El barbero agregó tiempo al servicio: se le avisa al cliente cuánto y a qué hora terminará aproximadamente. */
    public function serviceExtended(Appointment $appointment, int $extraMinutes, Carbon $newEnd): void
    {
        $appointment->loadMissing(['client.user', 'service']);

        $this->send($appointment->client?->getRelationValue('user'), $appointment,
            'Tu servicio se extendió', 'Tu servicio llevará un poco más',
            "Tu barbero agregó {$extraMinutes} minutos a tu servicio; terminará aproximadamente a las {$newEnd->format('H:i')}.",
            'Ver mi cita', $this->frontendUrl('/my/appointments'),
            '#5b8def', 'En proceso');
    }

    /** El servicio anterior se extendió y puede empezar tarde la cita de este cliente. */
    public function possibleDelay(Appointment $next, Carbon $estimatedStart): void
    {
        $next->loadMissing(['client.user', 'service']);

        $this->send($next->client?->getRelationValue('user'), $next,
            'Tu cita podría empezar un poco tarde', 'Pequeño retraso en tu cita',
            "El servicio anterior se está alargando; tu cita podría comenzar alrededor de las {$estimatedStart->format('H:i')}. Gracias por tu paciencia.",
            'Ver mi cita', $this->frontendUrl('/my/appointments'),
            '#f59e0b', 'Retraso');
    }

    /**
     * El cliente subio un comprobante de transferencia pendiente de revision.
     * Avisa a recepcion/admin para que lo revisen.
     */
    public function transferReceiptUploaded(Appointment $appointment, Payment $payment): void
    {
        $cliente = $appointment->client?->user?->name ?? 'Un cliente';
        $servicio = $appointment->service?->nombre ?? 'un servicio';

        $this->sendStaff($appointment,
            'Comprobante por revisar', 'Nuevo comprobante de transferencia',
            "{$cliente} subió un comprobante para {$servicio}. Revísalo antes de aprobarlo.",
            '#5b8def', 'Por revisar');
    }

    /**
     * Lista de espera: se liberó un horario para el barbero+servicio+fecha
     * que este usuario esperaba (ver WaitlistService::notifyIfAny()).
     * $freedAppointment es la cita cancelada/reagendada que originó el
     * aviso -- solo se usa como contexto (barbero/servicio/fecha), no
     * implica que el usuario tenga ninguna relación con ella.
     */
    public function waitlistSlotOpened(User $user, Appointment $freedAppointment): void
    {
        $freedAppointment->loadMissing(['barber.user', 'service']);
        $barbero = $freedAppointment->barber?->user?->name ?? 'tu barbero';
        $fecha = optional($freedAppointment->fecha)->format('d/m/Y') ?? 'la fecha que esperabas';
        $servicio = $freedAppointment->service?->nombre ?? 'el servicio';

        $this->send($user, $freedAppointment,
            'Se liberó un horario', '¡Se liberó el horario que esperabas!',
            "Se liberó un horario con {$barbero} el {$fecha} para {$servicio}. Entra a la app para reservarlo antes que alguien más.",
            'Reservar ahora', $this->frontendUrl('/reservar'),
            '#10b981', 'Disponible');
    }

    /**
     * Cargo por inasistencia (ver NoShowFeeService): avisa al cliente si se cobró solo a su tarjeta, si lo cubrió un
     * pago anticipado o si queda como adeudo por pagar en recepción (y que mientras tanto no puede reservar), y deja
     * constancia al personal cuando queda pendiente.
     */
    public function noShowFee(Appointment $appointment, NoShowFee $fee): void
    {
        $appointment->loadMissing(['client.user', 'service']);
        $fecha = optional($appointment->fecha)->format('d/m/Y') ?? 'la fecha indicada';
        $monto = '$'.number_format((float) ($fee->estado === NoShowFee::ESTADO_PENDIENTE ? $fee->monto : $fee->monto_base), 2);

        $clientUser = $appointment->client?->getRelationValue('user');

        if ($fee->estado === NoShowFee::ESTADO_PENDIENTE) {
            $this->send($clientUser, $appointment,
                'Tienes un adeudo por inasistencia', 'Cargo por no asistir',
                "No registramos tu asistencia a la cita del {$fecha}, así que se generó un cargo de {$monto}. Pásalo a pagar en recepción; mientras esté pendiente no podrás reservar nuevas citas.",
                'Ver mis citas', $this->frontendUrl('/my/appointments'),
                '#f59e0b', 'Adeudo');

            $this->sendStaff($appointment,
                'Adeudo por inasistencia', 'Cargo por inasistencia pendiente',
                "El cliente no asistió a su cita del {$fecha}: {$monto} pendientes de cobrar en sucursal.",
                '#f59e0b', 'Adeudo');

            return;
        }

        $detalle = $fee->metodo_cobro === 'tarjeta'
            ? "Cobramos {$monto} a tu tarjeta guardada."
            : 'Lo cubrimos con el pago que hiciste al reservar.';

        $this->send($clientUser, $appointment,
            'Cargo por inasistencia', 'Cargo por no asistir',
            "No registramos tu asistencia a la cita del {$fecha}. {$detalle}",
            'Ver mis citas', $this->frontendUrl('/my/appointments'),
            '#f59e0b', 'Cargo aplicado');
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
     * sola vez via Notification::send (batch), no una a una. Publico porque
     * StripeWebhookController tambien lo usa para avisar de pagos
     * fallidos/reembolsados/disputados -- no son eventos de "cita" en
     * sentido estricto, pero siempre estan ligados a una, y reusar este
     * mismo canal (AppointmentNotification a todo el staff) evita duplicar
     * la logica de "avisale a recepcion+admin".
     */
    public function sendStaff(Appointment $appointment, string $subject, string $title, string $message, string $accent = '#94a3b8', ?string $badge = null): void
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
                actionUrl: $this->frontendUrl('/appointments'),
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
     * URL de una página del frontend Nuxt (frontend-urban) — las páginas de
     * citas/agenda que estas notificaciones enlazaban ya no son rutas Blade.
     */
    private function frontendUrl(string $path): string
    {
        return config('app.frontend_url').$path;
    }
}
