<?php

namespace App\Services\Report;

use App\Models\Appointment;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Carbon\Carbon;

class ReportService
{
    // Sin filtro de fecha → últimos 30 días para proteger memoria
    private const DEFAULT_DAYS = 30;

    public function incomeReport(array $filters): array
    {
        $query = Payment::query()->with(['appointment.barber.user', 'appointment.client.user']);

        $this->applyDateRange($query, $filters, 'created_at');

        if (! empty($filters['barber_id'])) {
            $query->whereHas('appointment', fn ($q) => $q->where('barber_id', (string) $filters['barber_id']));
        }

        if (! empty($filters['metodo_pago'])) {
            $query->where('metodo_pago', $filters['metodo_pago']);
        }

        // Ingresos por servicios (citas cobradas).
        $serviceRows = $query->get()->map(function (Payment $payment) {
            return [
                'fecha' => optional($payment->created_at)->format('Y-m-d H:i'),
                'origen' => 'Servicio',
                'cliente' => $payment->appointment?->client?->user?->name,
                'barbero' => $payment->appointment?->barber?->user?->name,
                'metodo_pago' => $payment->metodo_pago,
                'monto' => (float) $payment->monto,
                'propina' => (float) $payment->propina,
                'total' => (float) $payment->monto + (float) $payment->propina,
            ];
        });

        // Ingresos por productos (pedidos entregados). Solo si no se filtra por
        // barbero (los pedidos de tienda no tienen barbero asignado).
        $orderRows = collect();
        if (empty($filters['barber_id'])) {
            $orderQuery = Order::query()->where('estado', 'entregado')->with('client.user');
            $this->applyDateRange($orderQuery, $filters, 'entregado_en');

            if (! empty($filters['metodo_pago'])) {
                $orderQuery->where('metodo_pago', $filters['metodo_pago']);
            }

            $orderRows = $orderQuery->get()->map(function (Order $order) {
                $total = (float) $order->total;

                return [
                    'fecha' => optional($order->entregado_en)->format('Y-m-d H:i'),
                    'origen' => 'Producto',
                    'cliente' => $order->client?->user?->name,
                    'barbero' => '—',
                    'metodo_pago' => $order->metodo_pago,
                    'monto' => $total,
                    'propina' => 0.0,
                    'total' => $total,
                ];
            });
        }

        $rows = $serviceRows->concat($orderRows)->sortByDesc('fecha')->values();

        return [
            'title' => 'Reporte de Ingresos',
            'headings' => ['Fecha', 'Origen', 'Cliente', 'Barbero', 'Método de pago', 'Monto', 'Propina', 'Total'],
            'rows' => $rows,
            'keys' => ['fecha', 'origen', 'cliente', 'barbero', 'metodo_pago', 'monto', 'propina', 'total'],
        ];
    }

    public function appointmentsReport(array $filters): array
    {
        $query = Appointment::query()->with(['barber.user', 'client.user', 'service']);

        // Carbon objects required — whereDate() is SQL-only, doesn't work on MongoDB UTCDateTime
        $this->applyDateRange($query, $filters, 'fecha');

        if (! empty($filters['barber_id'])) {
            $query->where('barber_id', (string) $filters['barber_id']);
        }

        if (! empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        $rows = $query->get()->map(function (Appointment $appointment) {
            return [
                'fecha' => optional($appointment->fecha)->format('Y-m-d'),
                'hora_inicio' => $appointment->hora_inicio,
                'hora_fin' => $appointment->hora_fin,
                'estado' => $appointment->estado,
                'cliente' => $appointment->client?->user?->name,
                'barbero' => $appointment->barber?->user?->name,
                'servicio' => $appointment->service?->nombre,
                'precio_cobrado' => (float) ($appointment->precio_cobrado ?? 0),
            ];
        });

        return [
            'title' => 'Reporte de Citas',
            'headings' => ['Fecha', 'Hora inicio', 'Hora fin', 'Estado', 'Cliente', 'Barbero', 'Servicio', 'Precio cobrado'],
            'rows' => $rows,
            'keys' => ['fecha', 'hora_inicio', 'hora_fin', 'estado', 'cliente', 'barbero', 'servicio', 'precio_cobrado'],
        ];
    }

    public function inventoryReport(array $filters): array
    {
        $query = Product::query();

        if (! empty($filters['categoria'])) {
            $query->where('categoria', $filters['categoria']);
        }

        if (! empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        $products = $query->get();
        $productIds = $products->pluck('id')->toArray();

        $movementCounts = ! empty($productIds)
            ? InventoryMovement::whereIn('product_id', $productIds)
                ->get(['product_id'])
                ->groupBy('product_id')
                ->map->count()
            : collect();

        $rows = $products->map(function (Product $product) use ($movementCounts) {
            return [
                'producto' => $product->nombre,
                'categoria' => $product->categoria,
                'tipo' => $product->tipo,
                'stock_actual' => (int) $product->stock_actual,
                'stock_minimo' => (int) $product->stock_minimo,
                'bajo_minimo' => $product->stock_actual <= $product->stock_minimo ? 'Sí' : 'No',
                'precio_venta' => (float) $product->precio_venta,
                'movimientos' => (int) ($movementCounts->get($product->id, 0)),
            ];
        });

        return [
            'title' => 'Reporte de Inventario',
            'headings' => ['Producto', 'Categoría', 'Tipo', 'Stock actual', 'Stock mínimo', 'Bajo mínimo', 'Precio venta', 'Movimientos'],
            'rows' => $rows,
            'keys' => ['producto', 'categoria', 'tipo', 'stock_actual', 'stock_minimo', 'bajo_minimo', 'precio_venta', 'movimientos'],
        ];
    }

    public function clientsReport(array $filters): array
    {
        $query = Appointment::query()->with('client.user');

        // Carbon objects required — string comparison against UTCDateTime returns 0 rows in MongoDB
        $this->applyDateRange($query, $filters, 'fecha');

        $rows = $query->get(['client_id', 'precio_cobrado', 'estado'])
            ->groupBy('client_id')
            ->map(function ($group) {
                return [
                    'cliente' => $group->first()->client?->user?->name,
                    'visitas' => $group->count(),
                    'completadas' => $group->where('estado', 'completada')->count(),
                    'gasto_total' => (float) $group->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0)),
                ];
            })
            ->sortByDesc('visitas')
            ->values();

        return [
            'title' => 'Reporte de Clientes',
            'headings' => ['Cliente', 'Citas totales', 'Completadas', 'Gasto total'],
            'rows' => $rows,
            'keys' => ['cliente', 'visitas', 'completadas', 'gasto_total'],
        ];
    }

    public function reportByType(string $type, array $filters): array
    {
        return match ($type) {
            'ingresos' => $this->incomeReport($filters),
            'citas' => $this->appointmentsReport($filters),
            'inventario' => $this->inventoryReport($filters),
            'clientes' => $this->clientsReport($filters),
            default => throw new \InvalidArgumentException('Tipo de reporte no soportado.'),
        };
    }

    /**
     * Aplica rango de fechas con Carbon objects (compatibles con MongoDB UTCDateTime).
     * Si no se especifican fechas, usa los últimos DEFAULT_DAYS días para proteger memoria.
     */
    private function applyDateRange($query, array $filters, string $field): void
    {
        $start = ! empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : Carbon::now()->subDays(self::DEFAULT_DAYS)->startOfDay();

        $end = ! empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : Carbon::now()->endOfDay();

        $query->where($field, '>=', $start)
            ->where($field, '<=', $end);
    }
}
