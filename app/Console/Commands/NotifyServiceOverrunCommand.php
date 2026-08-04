<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\Appointment\AppointmentNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyServiceOverrunCommand extends Command
{
    protected $signature = 'appointments:notify-service-overrun {--throttle=5 : Minutos entre avisos repetidos al mismo barbero}';

    protected $description = 'Avisa al barbero cuando un servicio "en_proceso" supera el tiempo estimado y sigue sin marcarse como completado';

    public function handle(AppointmentNotifier $notifier): int
    {
        $throttleMin = (int) $this->option('throttle');
        $tz = config('app.timezone', 'America/Mexico_City');

        $enProceso = Appointment::where('estado', 'en_proceso')->get();

        $avisadas = 0;

        foreach ($enProceso as $appt) {
            try {
                $finEsperado = $this->expectedEnd($appt, $tz);

                if (! $finEsperado || ! $finEsperado->isPast()) {
                    continue;
                }

                $ultimoAviso = $appt->ultimo_aviso_barbero_en;

                if ($ultimoAviso && Carbon::parse($ultimoAviso)->addMinutes($throttleMin)->isFuture()) {
                    continue;
                }

                $notifier->serviceOverrun($appt);
                $appt->update(['ultimo_aviso_barbero_en' => now()]);
                $avisadas++;
            } catch (\Throwable $e) {
                // una cita con datos raros no debe tumbar el resto del batch
            }
        }

        $this->info("Avisos de servicio con tiempo excedido enviados: {$avisadas}.");

        return self::SUCCESS;
    }

    /**
     * Fin esperado del servicio: hora real de inicio + duracion del servicio.
     * Si la cita es vieja y no tiene servicio_iniciado_en (creada antes de
     * este feature), usa fecha+hora_fin como respaldo.
     */
    private function expectedEnd(Appointment $appt, string $tz): ?Carbon
    {
        if ($appt->servicio_iniciado_en) {
            $duracion = (int) ($appt->service?->duracion_min ?? 30);

            return Carbon::parse($appt->servicio_iniciado_en, $tz)->addMinutes($duracion);
        }

        $fecha = optional($appt->fecha)->format('Y-m-d');
        if (! $fecha || ! $appt->hora_fin) {
            return null;
        }

        return Carbon::parse($fecha.' '.$appt->hora_fin, $tz);
    }
}
