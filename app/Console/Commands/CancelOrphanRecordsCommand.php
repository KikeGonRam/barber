<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Waitlist;
use App\Services\Appointment\AppointmentStatusService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Cancela citas próximas y anotaciones de lista de espera que apuntan a un cliente o barbero
 * que ya no existe (encontradas el 25-sep-2026 al verificar recepción: recepción las veía como
 * "Cliente por confirmar" y la lista de espera intentaría avisar a clientes borrados).
 *
 * Por defecto solo muestra lo que haría; con --apply cancela (nunca borra) y no envía avisos,
 * porque no hay a quién. No se ejecuta solo: no está en el scheduler.
 */
class CancelOrphanRecordsCommand extends Command
{
    protected $signature = 'data:cancel-orphans {--apply : Cancela de verdad; sin esta opción solo muestra lo que haría}';

    protected $description = 'Cancela citas y lista de espera activas cuyo cliente o barbero ya no existe';

    private const ACTIVE_APPOINTMENT = ['pendiente', 'confirmada', 'en_proceso'];

    private const ACTIVE_WAITLIST = [Waitlist::ESTADO_ACTIVO, Waitlist::ESTADO_NOTIFICADO];

    public function handle(AppointmentStatusService $statuses): int
    {
        $apply = (bool) $this->option('apply');

        /** @var Collection<int, Appointment> $appointments */
        $appointments = Appointment::with(['client.user', 'barber.user'])
            ->whereIn('estado', self::ACTIVE_APPOINTMENT)
            ->get()
            ->filter(fn (Appointment $a) => ! $this->hasUser($a, 'client') || ! $this->hasUser($a, 'barber'))
            ->values();

        /** @var Collection<int, Waitlist> $waitlist */
        $waitlist = Waitlist::with('client.user')
            ->whereIn('estado', self::ACTIVE_WAITLIST)
            ->get()
            ->filter(fn (Waitlist $w) => ! $this->hasUser($w, 'client'))
            ->values();

        $this->table(
            ['Cita', 'Fecha', 'Estado', 'Cliente', 'Barbero'],
            $appointments->map(fn (Appointment $a) => [
                (string) $a->getAttribute('code'),
                optional($a->getAttribute('fecha'))->toDateString().' '.$a->getAttribute('hora_inicio'),
                (string) $a->getAttribute('estado'),
                $this->hasUser($a, 'client') ? 'sí' : 'no existe',
                $this->hasUser($a, 'barber') ? 'sí' : 'no existe',
            ])->all()
        );
        $this->table(
            ['Lista de espera', 'Fecha', 'Estado'],
            $waitlist->map(fn (Waitlist $w) => [
                (string) $w->getKey(),
                optional($w->getAttribute('fecha'))->toDateString(),
                (string) $w->getAttribute('estado'),
            ])->all()
        );

        if (! $apply) {
            $this->info("Se cancelarían {$appointments->count()} citas y {$waitlist->count()} anotaciones de lista de espera. Nada cambió: agrega --apply para hacerlo.");

            return self::SUCCESS;
        }

        foreach ($appointments as $appointment) {
            $statuses->transition($appointment, 'cancelada');
        }
        foreach ($waitlist as $entry) {
            $entry->update(['estado' => Waitlist::ESTADO_CANCELADO]);
        }

        $this->info("Canceladas {$appointments->count()} citas y {$waitlist->count()} anotaciones de lista de espera.");

        return self::SUCCESS;
    }

    /** ¿La relación ($relation = client o barber) existe y tiene un usuario vivo? */
    private function hasUser(Model $record, string $relation): bool
    {
        $related = $record->getRelation($relation);

        return $related instanceof Model && $related->getRelation('user') instanceof Model;
    }
}
