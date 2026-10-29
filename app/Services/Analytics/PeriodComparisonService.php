<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Models\Client;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Comparación del periodo actual contra el anterior y cohortes de retención (auditoría UI/UX A-45).
 *
 * El «periodo anterior» es el mismo tramo del periodo calendario previo: si hoy es el día 12 del mes, se compara
 * el 1–12 de este mes con el 1–12 del mes pasado (acotado al fin del mes pasado), no con el mes pasado completo.
 * Así la variación no sale falsamente negativa a mitad de mes.
 */
class PeriodComparisonService
{
    private const PERIODOS = ['dia', 'semana', 'mes', 'trimestre', 'año'];

    public function startOf(string $period, CarbonInterface $now): Carbon
    {
        $now = Carbon::instance($now);

        return match ($period) {
            'dia' => $now->copy()->startOfDay(),
            'semana' => $now->copy()->startOfWeek(),
            'trimestre' => $now->copy()->startOfQuarter(),
            'año' => $now->copy()->startOfYear(),
            default => $now->copy()->startOfMonth(),
        };
    }

    /**
     * Ventanas actual y anterior de un periodo.
     *
     * @return array{actual: array{desde: Carbon, hasta: Carbon}, anterior: array{desde: Carbon, hasta: Carbon}}
     */
    public function windows(string $period, CarbonInterface $now): array
    {
        $period = in_array($period, self::PERIODOS, true) ? $period : 'mes';
        $now = Carbon::instance($now);
        $inicio = $this->startOf($period, $now);
        $transcurrido = $inicio->diffInSeconds($now);

        $inicioAnterior = match ($period) {
            'dia' => $inicio->copy()->subDay(),
            'semana' => $inicio->copy()->subWeek(),
            'trimestre' => $inicio->copy()->subMonthsNoOverflow(3),
            'año' => $inicio->copy()->subYear(),
            default => $inicio->copy()->subMonthNoOverflow(),
        };
        $finAnterior = $inicioAnterior->copy()->addSeconds((int) $transcurrido);
        $limite = $inicio->copy()->subSecond();
        if ($finAnterior->gt($limite)) {
            $finAnterior = $limite;
        }

        return [
            'actual' => ['desde' => $inicio, 'hasta' => $now],
            'anterior' => ['desde' => $inicioAnterior, 'hasta' => $finAnterior],
        ];
    }

    /**
     * Métricas del periodo actual y del anterior, con la variación porcentual.
     *
     * @return array<string, mixed>
     */
    public function compare(string $period, ?CarbonInterface $now = null): array
    {
        $period = in_array($period, self::PERIODOS, true) ? $period : 'mes';
        $now ??= Carbon::now();
        $windows = $this->windows($period, $now);
        $actual = $this->metrics($windows['actual']['desde'], $windows['actual']['hasta']);
        $anterior = $this->metrics($windows['anterior']['desde'], $windows['anterior']['hasta']);

        $metricas = [];
        foreach (array_keys($actual) as $clave) {
            $metricas[$clave] = [
                'actual' => $actual[$clave],
                'anterior' => $anterior[$clave],
                'variacion_pct' => $this->variation((float) $actual[$clave], (float) $anterior[$clave]),
            ];
        }

        return [
            'period' => $period,
            'actual' => ['desde' => $windows['actual']['desde']->toDateString(), 'hasta' => $windows['actual']['hasta']->toDateString()],
            'anterior' => ['desde' => $windows['anterior']['desde']->toDateString(), 'hasta' => $windows['anterior']['hasta']->toDateString()],
            'metricas' => $metricas,
        ];
    }

    /** Variación porcentual; null si no hay base de comparación (anterior en cero). */
    public function variation(float $actual, float $anterior): ?float
    {
        if ($anterior == 0.0) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    /**
     * @return array{ingresos: float, citas: int, completadas: int, perdidas: int, clientes_nuevos: int}
     */
    private function metrics(Carbon $desde, Carbon $hasta): array
    {
        $citas = Appointment::whereBetween('fecha', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->get(['estado', 'precio_cobrado']);

        $completadas = $citas->where('estado', 'completada');

        return [
            'ingresos' => round((float) $completadas->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0)), 2),
            'citas' => $citas->count(),
            'completadas' => $completadas->count(),
            'perdidas' => $citas->whereIn('estado', ['cancelada', 'no_asistio'])->count(),
            'clientes_nuevos' => Client::whereBetween('created_at', [$desde, $hasta])->count(),
        ];
    }

    /**
     * Cohortes de retención por mes de alta: de cada grupo de clientes que se registró en un mes, qué porcentaje
     * volvió a tener una cita completada en cada mes siguiente (0 = el mismo mes del alta).
     *
     * @return array{meses: int, cohortes: list<array{cohorte: string, clientes: int, retencion: list<int|null>}>}
     */
    public function cohorts(int $months = 6, ?CarbonInterface $now = null): array
    {
        $months = max(1, min(12, $months));
        $now = Carbon::instance($now ?? Carbon::now())->startOfMonth();
        $desde = $now->copy()->subMonthsNoOverflow($months - 1);

        $porCliente = Client::where('created_at', '>=', $desde)->get()
            ->mapWithKeys(fn ($c) => [(string) $c->id => Carbon::parse($c->created_at)->format('Y-m')]);

        $mesesCompletados = Appointment::where('estado', 'completada')
            ->where('fecha', '>=', $desde->copy()->startOfDay())
            ->whereIn('client_id', $porCliente->keys()->all())
            ->get(['client_id', 'fecha'])
            ->map(fn ($a) => [(string) $a->client_id, Carbon::parse($a->fecha)->format('Y-m')]);

        $cohortes = [];
        for ($i = 0; $i < $months; $i++) {
            $mesAlta = $desde->copy()->addMonthsNoOverflow($i);
            $clave = $mesAlta->format('Y-m');
            $ids = $porCliente->filter(fn ($mes) => $mes === $clave)->keys();
            $tamano = $ids->count();

            $retencion = [];
            for ($k = 0; $k < $months - $i; $k++) {
                $mesK = $mesAlta->copy()->addMonthsNoOverflow($k)->format('Y-m');
                $activos = $mesesCompletados
                    ->filter(fn ($par) => $par[1] === $mesK && $ids->contains($par[0]))
                    ->pluck(0)->unique()->count();
                $retencion[] = $tamano > 0 ? (int) round(($activos / $tamano) * 100) : null;
            }

            $cohortes[] = ['cohorte' => $clave, 'clientes' => $tamano, 'retencion' => $retencion];
        }

        return ['meses' => $months, 'cohortes' => $cohortes];
    }
}
