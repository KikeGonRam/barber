<?php

namespace App\Services\Report;

use App\Models\Appointment;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use Carbon\Carbon;

class ReportService
{
    public function incomeReport(array $filters): array
    {
        $query = Payment::query()->with(['appointment.barber.user', 'appointment.client.user']);

        $this->applyDateRange($query, $filters);

        if (! empty($filters['barber_id'])) {
            $query->whereHas('appointment', fn ($q) => $q->where('barber_id', (string) $filters['barber_id']));
        }

        if (! empty($filters['metodo_pago'])) {
            $query->where('metodo_pago', $filters['metodo_pago']);
        }

        $rows = $query->get()->map(function (Payment $payment) {
            return [
                'fecha' => optional($payment->created_at)->format('Y-m-d H:i'),
                'cliente' => $payment->appointment?->client?->user?->name,
                'barbero' => $payment->appointment?->barber?->user?->name,
                'metodo_pago' => $payment->metodo_pago,
                'monto' => (float) $payment->monto,
                'propina' => (float) $payment->propina,
                'total' => (float) $payment->monto + (float) $payment->propina,
            ];
        });

        return [
            'title' => 'Reporte de Ingresos',
            'headings' => ['Fecha', 'Cliente', 'Barbero', 'Método de pago', 'Monto', 'Propina', 'Total'],
            'rows' => $rows,
            'keys' => ['fecha', 'cliente', 'barbero', 'metodo_pago', 'monto', 'propina', 'total'],
        ];
    }

    public function appointmentsReport(array $filters): array
    {
        $query = Appointment::query()->with(['barber.user', 'client.user', 'service']);

        if (! empty($filters['start_date'])) {
            $query->whereDate('fecha', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('fecha', '<=', $filters['end_date']);
        }

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
        $movementCounts = !empty($productIds)
            ? InventoryMovement::whereIn('product_id', $productIds)->get(['product_id'])->groupBy('product_id')->map->count()
            : collect();

        $rows = $products->map(function (Product $product) use ($movementCounts) {
            return [
                'producto' => $product->nombre,
                'categoria' => $product->categoria,
                'tipo' => $product->tipo,
                'stock_actual' => (int) $product->stock_actual,
                'stock_minimo' => (int) $product->stock_minimo,
                'bajo_minimo' => $product->stock_actual <= $product->stock_minimo ? 'Sí' : 'No',
                'movimientos' => (int) ($movementCounts->get($product->id, 0)),
            ];
        });

        return [
            'title' => 'Reporte de Inventario',
            'headings' => ['Producto', 'Categoría', 'Tipo', 'Stock actual', 'Stock mínimo', 'Bajo mínimo', 'Movimientos'],
            'rows' => $rows,
            'keys' => ['producto', 'categoria', 'tipo', 'stock_actual', 'stock_minimo', 'bajo_minimo', 'movimientos'],
        ];
    }

    public function clientsReport(array $filters): array
    {
        $query = Appointment::query()->with('client.user');

        if (! empty($filters['start_date'])) {
            $query->where('fecha', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('fecha', '<=', $filters['end_date']);
        }

        $rows = $query->get(['client_id', 'precio_cobrado'])
            ->groupBy('client_id')
            ->map(function ($group) {
                return [
                    'cliente' => $group->first()->client?->user?->name,
                    'visitas' => $group->count(),
                    'gasto_total' => (float) $group->sum(fn($a) => (float)($a->precio_cobrado ?? 0)),
                ];
            })
            ->sortByDesc('visitas')
            ->values();

        return [
            'title' => 'Reporte de Clientes',
            'headings' => ['Cliente', 'Visitas', 'Gasto total'],
            'rows' => $rows,
            'keys' => ['cliente', 'visitas', 'gasto_total'],
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

    private function applyDateRange($query, array $filters): void
    {
        $start = ! empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $end = ! empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

        if ($start) {
            $query->where('created_at', '>=', $start);
        }

        if ($end) {
            $query->where('created_at', '<=', $end);
        }
    }
}
