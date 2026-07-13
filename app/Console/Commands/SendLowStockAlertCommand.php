<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Notifications\InventoryLowStockNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendLowStockAlertCommand extends Command
{
    protected $signature = 'inventory:low-stock-alert';

    protected $description = 'Notifica a administracion/recepcion los productos por debajo del stock minimo';

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
