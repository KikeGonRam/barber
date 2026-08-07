<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\Appointment\AppointmentNotifier;
use App\Services\Appointment\AppointmentStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Revisa las citas en estados "abiertos" (pendiente/confirmada) cuya hora fin ya
 * pasó (más un margen de gracia): a las confirmadas las marca "no_asistio" y a
 * las pendientes que nunca fueron aprobadas las cancela por expiración.
 * Se ejecuta cada hora vía el scheduler
 * (Schedule::command('appointments:mark-no-show')->hourly()).
 */
class MarkNoShowAppointmentsCommand extends Command
{
    protected $signature = 'appointments:mark-no-show {--grace=30 : Minutos de gracia tras la hora fin}';

    protected $description = 'Marca no_asistio las citas confirmadas ya vencidas y cancela las pendientes expiradas';

    /**
     * Recorre todas las citas abiertas y aplica la transición de estado que
     * corresponda según si estaban confirmadas o solo pendientes.
     */
    public function handle(AppointmentStatusService $status, AppointmentNotifier $notifier): int
    {
        $grace = (int) $this->option('grace');
        $tz = config('app.timezone', 'America/Mexico_City');

        // Solo estados "abiertos" (los demas son terminales). Conjunto pequeno.
        $open = Appointment::whereIn('estado', ['pendiente', 'confirmada'])->get();

        $noShow = 0;
        $expiradas = 0;

        foreach ($open as $appt) {
            $fecha = optional($appt->fecha)->format('Y-m-d');
            if (! $fecha) {
                continue;
            }

            $fin = Carbon::parse($fecha.' '.($appt->hora_fin ?: '23:59'), $tz)->addMinutes($grace);

            if (! $fin->isPast()) {
                continue;
            }

            try {
                if ($appt->estado === 'confirmada') {
                    // Cliente no se presento.
                    $status->transition($appt, 'no_asistio');
                    $notifier->statusChanged($appt, 'no_asistio');
                    $noShow++;
                } else {
                    // Pendiente que nunca fue aprobada y ya paso: expira.
                    $status->transition($appt, 'cancelada');
                    $notifier->cancelled($appt, 'expirada sin aprobacion');
                    $expiradas++;
                }
            } catch (\Throwable $e) {
                // transicion invalida u otro error: continuar con las demas
            }
        }

        $this->info("Citas marcadas: no_asistio={$noShow}, pendientes expiradas={$expiradas}.");

        return self::SUCCESS;
    }
}
