<?php

namespace App\Services\Barber;

use App\Models\Appointment;
use App\Models\Barber;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Compara el desempeño de cada barbero activo entre dos meses calendario
 * cerrados consecutivos, para BarberMonthlyPerformanceCommand: detecta
 * caídas fuertes (posible problema a atender) y al mejor mes (reconocimiento),
 * sin que nadie tenga que revisar el dashboard a mano para notarlo.
 */
class BarberPerformanceService
{
    // Caída de citas completadas vs. el mes anterior a partir de la cual se
    // considera "bajo rendimiento" y amerita avisar a administración.
    public const DROP_THRESHOLD_PCT = 40;

    // El mes anterior debe tener al menos esta cantidad de citas completadas
    // para que una caída porcentual sea significativa (evita ruido de un
    // barbero nuevo o con muy pocas citas de por sí).
    public const MIN_BASELINE_APPOINTMENTS = 5;

    /**
     * Reporte de un mes cerrado (por defecto, el mes calendario anterior a
     * $referenceDate) comparado contra el mes previo a ese.
     *
     * @return array{closed_month: string, top_performer: ?array, underperformers: array<int, array>}
     */
    public function monthlyReport(?Carbon $referenceDate = null): array
    {
        $referenceDate ??= now();

        $closedStart = $referenceDate->copy()->subMonthNoOverflow()->startOfMonth();
        $closedEnd = $referenceDate->copy()->subMonthNoOverflow()->endOfMonth();
        $priorStart = $referenceDate->copy()->subMonthsNoOverflow(2)->startOfMonth();
        $priorEnd = $referenceDate->copy()->subMonthsNoOverflow(2)->endOfMonth();

        $closedCounts = $this->completedCountsByBarber($closedStart, $closedEnd);
        $priorCounts = $this->completedCountsByBarber($priorStart, $priorEnd);

        $barbers = Barber::with('user')->where('activo', true)->get(['user_id', 'nombre', 'activo']);

        return [
            'closed_month' => $closedStart->translatedFormat('F Y'),
            'top_performer' => $this->findTopPerformer($barbers, $closedCounts),
            'underperformers' => $this->findUnderperformers($barbers, $closedCounts, $priorCounts),
        ];
    }

    /**
     * @return Collection<int|string, int<0, max>> citas completadas por barber_id en el rango dado.
     */
    private function completedCountsByBarber(Carbon $start, Carbon $end): Collection
    {
        // Carbon objects, no strings: whereBetween contra 'fecha' (cast 'date',
        // guardado como BSON UTCDateTime) no hace match si se le pasa un
        // string — ver BarberDashboardController para el mismo caso ya documentado.
        return Appointment::where('estado', 'completada')
            ->whereBetween('fecha', [$start, $end])
            ->get(['barber_id'])
            ->groupBy(fn ($a) => (string) $a->barber_id)
            ->map->count();
    }

    private function findTopPerformer(Collection $barbers, Collection $closedCounts): ?array
    {
        // Recorre de mayor a menor por si el conteo más alto pertenece a un
        // barbero ya inactivo/eliminado (sigue en el historial de citas pero
        // no en $barbers): el reconocimiento debe ir a alguien activo.
        foreach ($closedCounts->sortDesc() as $barberId => $count) {
            if ($count <= 0) {
                break;
            }

            $barber = $barbers->first(fn (Barber $b) => (string) $b->id === $barberId);

            if ($barber) {
                return [
                    'barber_id' => $barberId,
                    'nombre' => $barber->user?->name ?? $barber->nombre,
                    'citas' => $count,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<int, array{barber_id: string, nombre: string, citas_mes: int, citas_mes_anterior: int, caida_pct: int}>
     */
    private function findUnderperformers(Collection $barbers, Collection $closedCounts, Collection $priorCounts): array
    {
        $result = [];

        foreach ($barbers as $barber) {
            $barberId = (string) $barber->id;
            $priorCount = $priorCounts->get($barberId, 0);

            if ($priorCount < self::MIN_BASELINE_APPOINTMENTS) {
                continue;
            }

            $closedCount = $closedCounts->get($barberId, 0);
            $dropPct = (int) round((($priorCount - $closedCount) / $priorCount) * 100);

            if ($dropPct >= self::DROP_THRESHOLD_PCT) {
                $result[] = [
                    'barber_id' => $barberId,
                    'nombre' => $barber->user?->name ?? $barber->nombre,
                    'citas_mes' => $closedCount,
                    'citas_mes_anterior' => $priorCount,
                    'caida_pct' => $dropPct,
                ];
            }
        }

        return $result;
    }
}
