<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use Carbon\Carbon;

/**
 * Comisiones de barberos (roadmap P1): cuánto le corresponde a cada
 * barbero por sus citas completadas en un periodo. La base es el PRECIO DE
 * LISTA del servicio (Service::precio), no lo que efectivamente entró en
 * caja para esa transacción -- un barbero cobra por el servicio que dio,
 * no por si el negocio lo cobró con descuento de nivel, paquete prepagado,
 * gift card o premio de rifa (decisión explícita del dueño del negocio).
 *
 * Solo reporta: este primer alcance no registra pagos reales a barberos,
 * eso se sigue manejando fuera del sistema.
 */
class BarberCommissionService
{
    /**
     * @return array{
     *   periodo: array{desde: string, hasta: string},
     *   barberos: array<int, array{barber: array, citas_completadas: int, total_generado: float, comision_pct: float, comision_monto: float}>,
     *   total_generado: float,
     *   total_comisiones: float,
     * }
     */
    public function reportFor(Carbon $start, Carbon $end): array
    {
        $appointments = Appointment::where('estado', 'completada')
            ->whereBetween('fecha', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->with(['barber.user', 'service'])
            ->get();

        $rows = [];
        $totalGenerado = 0.0;
        $totalComisiones = 0.0;

        foreach ($appointments->groupBy('barber_id') as $barberId => $citas) {
            $barber = $citas->first()->barber;

            // Cita huérfana (barbero eliminado) -- no hay a quién pagarle,
            // pero tampoco se descarta en silencio: se reporta aparte.
            if (! $barber) {
                continue;
            }

            $generado = round((float) $citas->sum(fn (Appointment $a) => (float) ($a->service?->precio ?? 0)), 2);
            $pct = (float) $barber->comision_pct;
            $comision = round($generado * $pct / 100, 2);

            $rows[] = [
                'barber' => [
                    'id' => (string) $barber->id,
                    'nombre' => $barber->user?->name ?? $barber->nombre,
                ],
                'citas_completadas' => $citas->count(),
                'total_generado' => $generado,
                'comision_pct' => $pct,
                'comision_monto' => $comision,
            ];

            $totalGenerado += $generado;
            $totalComisiones += $comision;
        }

        // Mayor comisión primero: es lo que más le interesa revisar a quien
        // hace el reporte (quién genera más, quién falta configurar % > 0).
        usort($rows, fn ($a, $b) => $b['comision_monto'] <=> $a['comision_monto']);

        return [
            'periodo' => ['desde' => $start->toDateString(), 'hasta' => $end->toDateString()],
            'barberos' => $rows,
            'total_generado' => round($totalGenerado, 2),
            'total_comisiones' => round($totalComisiones, 2),
        ];
    }
}
