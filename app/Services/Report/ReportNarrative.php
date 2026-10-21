<?php

namespace App\Services\Report;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Convierte las filas de un reporte en el contenido "ejecutivo" que acompaña al PDF y al Excel:
 * periodo, objetivo, indicadores clave y hallazgos redactados. Todo sale de las mismas filas que ya
 * calcula ReportService, así que el documento y la tabla nunca se contradicen.
 */
class ReportNarrative
{
    private const PURPOSE = [
        'ingresos' => 'Resume el dinero que entró a la barbería por servicios cobrados y por productos entregados, con su propina y su método de pago.',
        'citas' => 'Resume la operación de la agenda: cuántas citas hubo, cómo terminaron y quién y qué servicios concentran la demanda.',
        'inventario' => 'Resume el estado de existencias de los productos y señala cuáles necesitan reposición.',
        'clientes' => 'Resume el comportamiento de los clientes: cuántos visitaron, con qué frecuencia y cuánto gastaron.',
    ];

    /**
     * @param  array{rows: Collection, title: string, chart?: array|null}  $report
     * @return array{period: string, purpose: string, scope: array<int, array{0: string, 1: string}>, kpis: array<int, array{label: string, value: string, hint: string}>, findings: array<int, string>}
     */
    public function build(string $type, array $report, array $filters): array
    {
        $rows = $report['rows'];
        $analysis = match ($type) {
            'ingresos' => $this->income($rows),
            'citas' => $this->appointments($rows),
            'inventario' => $this->inventory($rows),
            'clientes' => $this->clients($rows),
            default => ['kpis' => [], 'findings' => []],
        };

        return [
            'period' => $this->period($type, $filters),
            'purpose' => self::PURPOSE[$type] ?? '',
            'scope' => $this->scope($filters),
            'kpis' => $analysis['kpis'],
            'findings' => $analysis['findings'] ?: ['No hay registros suficientes en el periodo para generar hallazgos.'],
        ];
    }

    private function period(string $type, array $filters): string
    {
        if ($type === 'inventario') {
            return 'Existencias al '.Carbon::now()->format('d/m/Y');
        }

        $start = ! empty($filters['start_date']) ? Carbon::parse($filters['start_date']) : Carbon::now()->subDays(30);
        $end = ! empty($filters['end_date']) ? Carbon::parse($filters['end_date']) : Carbon::now();

        return $start->format('d/m/Y').' al '.$end->format('d/m/Y');
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function scope(array $filters): array
    {
        $labels = [
            'barber_id' => 'Barbero', 'metodo_pago' => 'Método de pago', 'estado' => 'Estado',
            'categoria' => 'Categoría', 'tipo' => 'Tipo',
        ];
        $scope = [];
        foreach ($labels as $key => $label) {
            if (! empty($filters[$key])) {
                $scope[] = [$label, (string) $filters[$key]];
            }
        }

        return $scope ?: [['Filtros', 'Sin filtros adicionales (todos los registros del periodo)']];
    }

    private function money(float $value): string
    {
        return '$'.number_format($value, 2);
    }

    private function pct(float $part, float $whole): string
    {
        return $whole > 0 ? number_format($part / $whole * 100, 1).' %' : '0 %';
    }

    /** Etiqueta con mayor suma/conteo: [etiqueta, valor] o null si no hay datos. */
    private function top(Collection $rows, string $key, ?string $sumKey = null): ?array
    {
        $groups = $rows->filter(fn ($r) => ! empty($r[$key]) && $r[$key] !== '—')
            ->groupBy($key)
            ->map(fn ($g) => $sumKey ? $g->sum($sumKey) : $g->count())
            ->sortDesc();

        return $groups->isEmpty() ? null : [(string) $groups->keys()->first(), (float) $groups->first()];
    }

    private function income(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return ['kpis' => [], 'findings' => []];
        }

        $total = (float) $rows->sum('total');
        $tips = (float) $rows->sum('propina');
        $services = (float) $rows->where('origen', 'Servicio')->sum('total');
        $byDay = $rows->groupBy(fn ($r) => substr((string) $r['fecha'], 0, 10))->map(fn ($g) => $g->sum('total'))->sortDesc();
        $method = $this->top($rows, 'metodo_pago', 'total');
        $barber = $this->top($rows, 'barbero', 'total');
        $days = max($byDay->count(), 1);

        $findings = [
            sprintf('Se registraron %s operaciones por un total de %s, con un ticket promedio de %s.', number_format($rows->count()), $this->money($total), $this->money($total / $rows->count())),
            sprintf('Los servicios aportaron el %s del ingreso y los productos el %s restante.', $this->pct($services, $total), $this->pct($total - $services, $total)),
            sprintf('El mejor día fue %s con %s; el ingreso diario promedio fue de %s.', Carbon::parse($byDay->keys()->first())->translatedFormat('d \d\e F'), $this->money((float) $byDay->first()), $this->money($total / $days)),
        ];
        if ($tips > 0) {
            $findings[] = sprintf('Las propinas sumaron %s (%s del total cobrado).', $this->money($tips), $this->pct($tips, $total));
        }
        if ($method) {
            $findings[] = sprintf('%s es el método de pago dominante: concentra %s del ingreso.', ucfirst($method[0]), $this->pct($method[1], $total));
        }
        if ($barber) {
            $findings[] = sprintf('%s generó el mayor ingreso por servicios: %s.', $barber[0], $this->money($barber[1]));
        }

        return [
            'kpis' => [
                ['label' => 'Ingreso total', 'value' => $this->money($total), 'hint' => 'servicios + productos'],
                ['label' => 'Operaciones', 'value' => number_format($rows->count()), 'hint' => 'cobros y entregas'],
                ['label' => 'Ticket promedio', 'value' => $this->money($total / $rows->count()), 'hint' => 'por operación'],
                ['label' => 'Propinas', 'value' => $this->money($tips), 'hint' => $this->pct($tips, $total).' del total'],
            ],
            'findings' => $findings,
        ];
    }

    private function appointments(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return ['kpis' => [], 'findings' => []];
        }

        $count = $rows->count();
        $done = $rows->where('estado', 'completada')->count();
        $cancelled = $rows->where('estado', 'cancelada')->count();
        $revenue = (float) $rows->sum('precio_cobrado');
        $barber = $this->top($rows, 'barbero');
        $service = $this->top($rows, 'servicio');
        $hour = $rows->filter(fn ($r) => ! empty($r['hora_inicio']))
            ->groupBy(fn ($r) => substr((string) $r['hora_inicio'], 0, 2))->map->count()->sortDesc();

        $findings = [
            sprintf('La agenda tuvo %s citas: %s completadas (%s) y %s canceladas (%s).', number_format($count), number_format($done), $this->pct($done, $count), number_format($cancelled), $this->pct($cancelled, $count)),
        ];
        if ($revenue > 0) {
            $findings[] = sprintf('Las citas cobradas representan %s en servicios.', $this->money($revenue));
        }
        if ($barber) {
            $findings[] = sprintf('%s atendió más citas: %s (%s de la agenda).', $barber[0], number_format($barber[1]), $this->pct($barber[1], $count));
        }
        if ($service) {
            $findings[] = sprintf('El servicio más pedido fue «%s» con %s citas.', $service[0], number_format($service[1]));
        }
        if ($hour->isNotEmpty()) {
            $findings[] = sprintf('La hora de mayor demanda es las %s:00, con %s citas.', $hour->keys()->first(), number_format($hour->first()));
        }

        return [
            'kpis' => [
                ['label' => 'Citas', 'value' => number_format($count), 'hint' => 'en el periodo'],
                ['label' => 'Completadas', 'value' => $this->pct($done, $count), 'hint' => number_format($done).' citas'],
                ['label' => 'Canceladas', 'value' => $this->pct($cancelled, $count), 'hint' => number_format($cancelled).' citas'],
                ['label' => 'Facturado en citas', 'value' => $this->money($revenue), 'hint' => 'precio cobrado'],
            ],
            'findings' => $findings,
        ];
    }

    private function inventory(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return ['kpis' => [], 'findings' => []];
        }

        $low = $rows->where('bajo_minimo', 'Sí');
        $value = (float) $rows->sum(fn ($r) => $r['stock_actual'] * $r['precio_venta']);
        $moved = $rows->sortByDesc('movimientos')->first();
        $category = $this->top($rows, 'categoria');

        $findings = [
            sprintf('Se controlan %s productos con %s unidades en existencia, valuadas a precio de venta en %s.', number_format($rows->count()), number_format((int) $rows->sum('stock_actual')), $this->money($value)),
            $low->isEmpty()
                ? 'Ningún producto está en o por debajo de su stock mínimo.'
                : sprintf('%s productos están en o por debajo de su mínimo y necesitan reposición: %s.', number_format($low->count()), $low->sortBy('stock_actual')->take(3)->pluck('producto')->implode(', ')),
        ];
        if (($moved['movimientos'] ?? 0) > 0) {
            $findings[] = sprintf('«%s» es el producto con más movimientos de inventario (%s).', $moved['producto'], number_format($moved['movimientos']));
        }
        if ($category) {
            $findings[] = sprintf('La categoría con más productos es «%s» (%s).', $category[0], number_format($category[1]));
        }

        return [
            'kpis' => [
                ['label' => 'Productos', 'value' => number_format($rows->count()), 'hint' => 'en catálogo'],
                ['label' => 'Bajo mínimo', 'value' => number_format($low->count()), 'hint' => 'requieren reposición'],
                ['label' => 'Unidades', 'value' => number_format((int) $rows->sum('stock_actual')), 'hint' => 'en existencia'],
                ['label' => 'Valor de existencias', 'value' => $this->money($value), 'hint' => 'a precio de venta'],
            ],
            'findings' => $findings,
        ];
    }

    private function clients(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return ['kpis' => [], 'findings' => []];
        }

        $visits = (int) $rows->sum('visitas');
        $spend = (float) $rows->sum('gasto_total');
        $top = $rows->sortByDesc('gasto_total')->first();
        $top3 = (float) $rows->sortByDesc('gasto_total')->take(3)->sum('gasto_total');
        $repeat = $rows->where('visitas', '>', 1)->count();

        $findings = [
            sprintf('%s clientes agendaron %s citas, en promedio %s por cliente.', number_format($rows->count()), number_format($visits), number_format($visits / $rows->count(), 1)),
            sprintf('%s clientes (%s) volvieron más de una vez.', number_format($repeat), $this->pct($repeat, $rows->count())),
        ];
        if ($spend > 0) {
            $findings[] = sprintf('%s es quien más gastó: %s. Los tres clientes principales concentran %s del gasto.', $top['cliente'], $this->money((float) $top['gasto_total']), $this->pct($top3, $spend));
        }

        return [
            'kpis' => [
                ['label' => 'Clientes', 'value' => number_format($rows->count()), 'hint' => 'con citas en el periodo'],
                ['label' => 'Citas', 'value' => number_format($visits), 'hint' => number_format($visits / $rows->count(), 1).' por cliente'],
                ['label' => 'Recurrentes', 'value' => $this->pct($repeat, $rows->count()), 'hint' => number_format($repeat).' clientes'],
                ['label' => 'Gasto total', 'value' => $this->money($spend), 'hint' => 'en el periodo'],
            ],
            'findings' => $findings,
        ];
    }
}
