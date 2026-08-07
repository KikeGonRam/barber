<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Notifications\InventoryLowStockNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Revisa el inventario de productos activos y notifica a administradores y
 * recepcionistas cuáles están en o por debajo de su stock mínimo. Se ejecuta
 * a diario a las 09:00 vía el scheduler
 * (Schedule::command('inventory:low-stock-alert')->dailyAt('09:00')).
 */
class SendLowStockAlertCommand extends Command
{
    protected $signature = 'inventory:low-stock-alert';

    protected $description = 'Notifica a administracion/recepcion los productos por debajo del stock minimo';

    /**
     * Busca productos con stock bajo y, si hay alguno, notifica a los usuarios
     * con rol administrador o recepcionista.
     */
    public function handle(): int
    {
        // Productos activos en o por debajo de su minimo (comparacion campo-a-campo en Mongo).
        $lowStock = Product::where('activo', true)
            ->whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])
            ->get(['nombre', 'stock_actual', 'stock_minimo']);

        if ($lowStock->isEmpty()) {
            $this->info('Sin productos con stock bajo. No se envian alertas.');

            return self::SUCCESS;
        }

        $products = $lowStock->map(fn ($p) => [
            'nombre' => (string) $p->nombre,
            'stock_actual' => (int) $p->stock_actual,
            'stock_minimo' => (int) $p->stock_minimo,
        ])->all();

        $staff = User::whereRoleName(['administrador', 'recepcionista'])->get();

        if ($staff->isEmpty()) {
            $this->warn('No hay usuarios admin/recepcion a quienes notificar.');

            return self::SUCCESS;
        }

        Notification::send($staff, new InventoryLowStockNotification($products));

        $this->info("Alerta de stock bajo enviada a {$staff->count()} usuario(s) por {$lowStock->count()} producto(s).");

        return self::SUCCESS;
    }
}
