<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\DailySummaryNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendDailySummaryCommand extends Command
{
    protected $signature = 'reports:daily-summary';

    protected $description = 'Envia a administracion el resumen de cierre de jornada del dia';

    public function handle(): int
    {
        $today = Carbon::today();

        $todaysAppointments = Appointment::whereDate('fecha', $today)->get(['estado']);
        $total = $todaysAppointments->count();
        $completadas = $todaysAppointments->where('estado', 'completada')->count();
        $canceladas = $todaysAppointments->where('estado', 'cancelada')->count();

        $incomeServicios = Payment::whereDate('created_at', $today)
            ->get(['monto', 'propina'])
            ->sum(fn ($p) => (float) $p->monto + (float) $p->propina);

        // Ventas de tienda (pedidos entregados hoy).
        $ventasTienda = Order::where('estado', 'entregado')
            ->whereDate('entregado_en', $today)
            ->get(['total'])
            ->sum(fn ($o) => (float) $o->total);

        $lowStock = Product::where('activo', true)
            ->whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])
            ->count();

        $ocupacion = $total > 0 ? round(($completadas / $total) * 100).'%' : '—';

        $stats = [
            'Ingresos totales' => '$'.number_format($incomeServicios + $ventasTienda, 2),
            '  Servicios' => '$'.number_format($incomeServicios, 2),
            '  Tienda' => '$'.number_format($ventasTienda, 2),
            'Citas totales' => (string) $total,
            'Completadas' => (string) $completadas,
            'Canceladas' => (string) $canceladas,
            'Tasa de completadas' => $ocupacion,
            'Productos con stock bajo' => (string) $lowStock,
        ];

        $admins = User::whereRoleName('administrador')->get();

        if ($admins->isEmpty()) {
            $this->warn('No hay administradores a quienes enviar el resumen.');

            return self::SUCCESS;
        }

        Notification::send($admins, new DailySummaryNotification($stats, $today->translatedFormat('l d \d\e F, Y')));

        $this->info("Resumen del dia enviado a {$admins->count()} administrador(es).");

        return self::SUCCESS;
    }
}
