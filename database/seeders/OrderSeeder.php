<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    private const METODOS = ['efectivo', 'tarjeta', 'transferencia', 'qr'];

    private const STANDALONE_ORDERS = 3000;

    private const TODAY_PENDING_ORDERS = 20;

    public function run(): void
    {
        $products = Product::query()->where('precio_venta', '>', 0)->get(['_id', 'nombre', 'precio_venta'])
            ->map(fn ($p) => ['nombre' => $p->nombre, 'precio' => (float) $p->precio_venta])
            ->all();

        if (empty($products)) {
            $this->command->warn('No hay productos de venta; se omite OrderSeeder.');

            return;
        }

        $usedFolios = [];
        $rows = [];
        $total = 0;

        // 1) Add-ons de productos ligados a ~20% de las citas completadas.
        Appointment::query()
            ->where('estado', 'completada')
            ->select(['_id', 'client_id', 'created_at', 'metodo_pago'])
            ->chunkById(2000, function ($appointments) use (&$rows, &$total, &$usedFolios, $products) {
                foreach ($appointments as $appt) {
                    if (random_int(1, 100) > 20) {
                        continue;
                    }
                    $items = $this->randomItems($products);
                    $timestamp = $appt->created_at ?? now();

                    $rows[] = $this->buildRow(
                        clientId: (string) $appt->client_id,
                        items: $items,
                        estado: 'entregado',
                        tipo: 'cita',
                        appointmentId: (string) $appt->id,
                        metodoPago: $appt->metodo_pago ?? 'efectivo',
                        entregadoEn: $timestamp,
                        createdAt: $timestamp,
                        usedFolios: $usedFolios,
                    );
                    $total++;

                    if (count($rows) >= 3000) {
                        Order::insert($rows);
                        $rows = [];
                    }
                }
            });

        if (! empty($rows)) {
            Order::insert($rows);
            $rows = [];
        }

        // 2) Pedidos sueltos de tienda (historicos, entregados en su mayoria).
        $clientIds = Client::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $start = Carbon::create(2024, 1, 1);
        $today = Carbon::today();

        for ($i = 0; $i < self::STANDALONE_ORDERS; $i++) {
            $clientId = $clientIds[array_rand($clientIds)];
            $items = $this->randomItems($products);
            $fecha = $start->copy()->addDays(random_int(0, (int) $start->diffInDays($today)))->addHours(random_int(9, 20));
            $estado = random_int(1, 100) <= 90 ? 'entregado' : 'cancelado';

            $rows[] = $this->buildRow(
                clientId: $clientId,
                items: $items,
                estado: $estado,
                tipo: 'tienda',
                appointmentId: null,
                metodoPago: $estado === 'entregado' ? self::METODOS[array_rand(self::METODOS)] : null,
                entregadoEn: $estado === 'entregado' ? $fecha : null,
                createdAt: $fecha,
                usedFolios: $usedFolios,
            );
            $total++;

            if (count($rows) >= 3000) {
                Order::insert($rows);
                $rows = [];
            }
        }

        // 3) Pedidos pendientes de HOY, para que la bandeja de recepcion tenga contenido real.
        for ($i = 0; $i < self::TODAY_PENDING_ORDERS; $i++) {
            $clientId = $clientIds[array_rand($clientIds)];
            $items = $this->randomItems($products);
            $createdAt = Carbon::today()->copy()->addHours(random_int(8, 19))->addMinutes(random_int(0, 59));

            $rows[] = $this->buildRow(
                clientId: $clientId,
                items: $items,
                estado: 'pendiente',
                tipo: 'tienda',
                appointmentId: null,
                metodoPago: null,
                entregadoEn: null,
                createdAt: $createdAt,
                usedFolios: $usedFolios,
            );
            $total++;
        }

        if (! empty($rows)) {
            Order::insert($rows);
        }

        $this->command->info("Pedidos sembrados: {$total}");
    }

    /** @return array<int,array{nombre:string,cantidad:int,precio:float,subtotal:float}> */
    private function randomItems(array $products): array
    {
        $count = random_int(1, 3);
        $picked = collect($products)->random(min($count, count($products)))->values();

        return $picked->map(function ($p) {
            $cantidad = random_int(1, 2);

            return [
                'nombre' => $p['nombre'],
                'cantidad' => $cantidad,
                'precio' => $p['precio'],
                'subtotal' => round($p['precio'] * $cantidad, 2),
            ];
        })->all();
    }

    private function buildRow(
        string $clientId,
        array $items,
        string $estado,
        string $tipo,
        ?string $appointmentId,
        ?string $metodoPago,
        ?Carbon $entregadoEn,
        Carbon $createdAt,
        array &$usedFolios,
    ): array {
        $total = array_sum(array_column($items, 'subtotal'));

        return [
            'client_id' => $clientId,
            'folio' => $this->uniqueFolio($usedFolios),
            'items' => $items,
            'total' => round($total, 2),
            'estado' => $estado,
            'tipo' => $tipo,
            'appointment_id' => $appointmentId,
            'metodo_pago' => $metodoPago,
            'entregado_en' => $entregadoEn,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    private function uniqueFolio(array &$used): string
    {
        do {
            $folio = 'P-'.strtoupper(Str::random(6));
        } while (isset($used[$folio]));
        $used[$folio] = true;

        return $folio;
    }
}
