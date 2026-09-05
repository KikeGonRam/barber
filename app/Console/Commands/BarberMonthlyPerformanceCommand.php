<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\BarberPerformanceReportNotification;
use App\Services\Barber\BarberPerformanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Corre el día 1 de cada mes: compara el desempeño de cada barbero activo del
 * mes recién cerrado contra el anterior (BarberPerformanceService) y notifica
 * a administración el mejor mes y cualquier caída fuerte que amerite atención.
 * Antes de esto, "rendimiento de barberos" era solo un reporte que había que
 * ir a revisar a mano — nadie se enteraba de una caída si no entraba a mirar.
 */
class BarberMonthlyPerformanceCommand extends Command
{
    protected $signature = 'barbers:monthly-performance {--dry-run : Solo muestra el resultado, sin enviar notificaciones}';

    protected $description = 'Notifica a administracion el desempeno mensual de barberos (mejor mes y caidas fuertes)';

    public function handle(BarberPerformanceService $performance): int
    {
        $report = $performance->monthlyReport();

        $this->info("Mes evaluado: {$report['closed_month']}");
        $this->info($report['top_performer']
            ? "Mejor mes: {$report['top_performer']['nombre']} ({$report['top_performer']['citas']} citas)"
            : 'Sin datos suficientes para un mejor mes.');
        $this->info(count($report['underperformers']).' barbero(s) con caida fuerte.');

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (! $report['top_performer'] && empty($report['underperformers'])) {
            $this->info('Nada que reportar este mes; no se envian notificaciones.');

            return self::SUCCESS;
        }

        $admins = User::whereRoleName('administrador')->get();

        if ($admins->isEmpty()) {
            $this->warn('No hay usuarios administrador a quienes notificar.');

            return self::SUCCESS;
        }

        Notification::send($admins, new BarberPerformanceReportNotification(
            closedMonth: $report['closed_month'],
            topPerformer: $report['top_performer'],
            underperformers: $report['underperformers'],
        ));

        $this->info("Reporte enviado a {$admins->count()} administrador(es).");

        return self::SUCCESS;
    }
}
