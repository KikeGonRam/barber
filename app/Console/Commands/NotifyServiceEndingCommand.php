<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\Appointment\AppointmentNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Avisa al barbero unos minutos ANTES de que termine el servicio en curso (por defecto 5), para que pueda terminarlo
 * ya o agregar tiempo (ServiceTimeService). Una sola vez por fin esperado: al extender el servicio, el aviso se vuelve
 * a armar para el nuevo fin. Si el servicio ya se pasó, de eso se encarga NotifyServiceOverrunCommand.
 *
 * Corre cada minuto (Schedule::command('appointments:notify-service-ending')->everyMinute()).
 */
class NotifyServiceEndingCommand extends Command
{
    protected $signature = 'appointments:notify-service-ending {--minutes=5 : Minutos antes del fin en que se avisa}';

    protected $description = 'Avisa al barbero que el servicio en curso está por terminar para que lo cierre o agregue tiempo';

    public function handle(AppointmentNotifier $notifier): int
    {
        $window = max(1, (int) $this->option('minutes'));
        $tz = (string) config('app.timezone', 'America/Mexico_City');
        $sent = 0;

        foreach (Appointment::where('estado', 'en_proceso')->whereNull('aviso_fin_enviado_en')->get() as $appointment) {
            try {
                $end = $appointment->expectedServiceEnd($tz);
                if (! $end || $end->isPast()) {
                    continue;
                }

                $left = (int) ceil(now()->diffInSeconds($end, false) / 60);
                if ($left > $window) {
                    continue;
                }

                $notifier->serviceEnding($appointment, $left);
                $appointment->update(['aviso_fin_enviado_en' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                // Una cita con datos raros no debe tumbar el resto del lote.
                Log::warning('Fallo aviso de servicio por terminar', [
                    'appointment_id' => (string) $appointment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Avisos de servicio por terminar enviados: {$sent}.");

        return self::SUCCESS;
    }
}
