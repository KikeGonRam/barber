<?php

namespace App\Services\Appointment;

use App\Exceptions\Domain\WaitlistException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\Waitlist;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Lista de espera (roadmap P1): cuando ya no hay horarios disponibles para
 * un barbero+servicio+fecha, un cliente puede anotarse para que se le avise
 * si se libera uno (cancelación o reagendamiento a otro día). Vive separada
 * de AppointmentService porque no reserva nada por sí sola -- solo notifica;
 * el backend de citas sigue siendo la única autoridad sobre el hueco real.
 */
class WaitlistService
{
    public function __construct(
        private readonly AppointmentService $appointments,
        private readonly AppointmentNotifier $notifier,
    ) {}

    /**
     * Anota al cliente. Exige que el día esté realmente lleno para ese
     * barbero+servicio -- evita que alguien se anote "por si acaso" a una
     * lista pensada para avisar de huecos que de otro modo no existirían.
     */
    public function join(Client $client, Barber $barber, Service $service, string $fecha): Waitlist
    {
        $slots = $this->appointments->getAvailableSlots($barber, $fecha, $service);

        if (! empty($slots)) {
            throw new WaitlistException('Todavía hay horarios disponibles ese día -- puedes reservar directamente.');
        }

        // Check de aplicación para un mensaje de error limpio en el caso
        // normal (no atómico); el índice único compuesto de la migración
        // (client_id+barber_id+service_id+fecha+activa) es la garantía real
        // ante dos solicitudes casi simultáneas, igual patrón que
        // PaymentRepository::existsForAppointment() + su índice.
        $exists = Waitlist::where('client_id', (string) $client->id)
            ->where('barber_id', (string) $barber->id)
            ->where('service_id', (string) $service->id)
            ->whereDate('fecha', $fecha)
            ->whereIn('estado', [Waitlist::ESTADO_ACTIVO, Waitlist::ESTADO_NOTIFICADO])
            ->exists();

        if ($exists) {
            throw new WaitlistException('Ya estás en la lista de espera para ese barbero, servicio y fecha.');
        }

        try {
            return Waitlist::create([
                'client_id' => (string) $client->id,
                'barber_id' => (string) $barber->id,
                'service_id' => (string) $service->id,
                'fecha' => $fecha,
                'estado' => Waitlist::ESTADO_ACTIVO,
            ]);
        } catch (BulkWriteException $e) {
            throw new WaitlistException('Ya estás en la lista de espera para ese barbero, servicio y fecha.');
        }
    }

    /**
     * El cliente se da de baja de su propia anotación.
     */
    public function cancel(Waitlist $entry): void
    {
        if (! in_array($entry->estado, [Waitlist::ESTADO_ACTIVO, Waitlist::ESTADO_NOTIFICADO], true)) {
            throw new WaitlistException('Esta anotación ya no está activa.');
        }

        $entry->update(['estado' => Waitlist::ESTADO_CANCELADO]);
    }

    /**
     * Se llama cuando una cita libera su horario (cancelación, o
     * reagendamiento que cambia de fecha) -- avisa A TODOS los anotados para
     * ese barbero+servicio+fecha a la vez. El primero en reservar de verdad
     * se lo queda; los demás, cuando intenten, chocan con la disponibilidad
     * real (AppointmentService la sigue calculando en el momento).
     */
    public function notifyIfAny(Appointment $freedAppointment): void
    {
        $entries = Waitlist::where('barber_id', (string) $freedAppointment->barber_id)
            ->where('service_id', (string) $freedAppointment->service_id)
            ->whereDate('fecha', $freedAppointment->fecha)
            ->where('estado', Waitlist::ESTADO_ACTIVO)
            ->with('client.user')
            ->get();

        foreach ($entries as $entry) {
            $entry->update(['estado' => Waitlist::ESTADO_NOTIFICADO, 'notificado_en' => now()]);

            if ($user = $entry->client?->user) {
                $this->notifier->waitlistSlotOpened($user, $freedAppointment);
            }
        }
    }

    /**
     * Anotaciones cuya fecha ya pasó sin que nadie reservara: quedan
     * `expirado` en vez de acumularse indefinidamente como activo/notificado.
     * Llamado por WaitlistExpireStaleEntriesCommand (diario).
     */
    public function expireStale(): int
    {
        return Waitlist::whereIn('estado', [Waitlist::ESTADO_ACTIVO, Waitlist::ESTADO_NOTIFICADO])
            ->where('fecha', '<', now()->startOfDay())
            ->update(['estado' => Waitlist::ESTADO_EXPIRADO, 'activa' => false]);
    }
}
