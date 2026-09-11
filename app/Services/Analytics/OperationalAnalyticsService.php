<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Models\Payment;

/** Métricas operativas actuales, sin Spark, LLM ni datos personales. */
class OperationalAnalyticsService
{
    public function summary(): array
    {
        $today = now()->startOfDay();
        $start = $today->copy()->subDays(29);
        $todayStates = Appointment::whereDate('fecha', $today)->pluck('estado');
        $periodStates = Appointment::whereBetween('fecha', [$start, $today->copy()->endOfDay()])->pluck('estado');
        $pending = $todayStates->filter(fn ($state) => $state === 'pendiente')->count();
        $transfers = Payment::where('estado', Payment::ESTADO_PENDIENTE_VERIFICACION)->count();
        $cancelled = $periodStates->filter(fn ($state) => $state === 'cancelada')->count();
        $noShows = $periodStates->filter(fn ($state) => $state === 'no_asistio')->count();

        return [
            'calculated_at' => now()->toAtomString(),
            'source' => 'appointments/payments',
            'period' => ['start' => $start->toDateString(), 'end' => $today->toDateString(), 'timezone' => config('app.timezone')],
            'kpis' => [
                ['label' => 'Citas de hoy', 'value' => $todayStates->count(), 'detail' => 'Todas las citas registradas para hoy'],
                ['label' => 'Por confirmar hoy', 'value' => $pending, 'detail' => 'Estado pendiente'],
                ['label' => 'Completadas hoy', 'value' => $todayStates->filter(fn ($state) => $state === 'completada')->count(), 'detail' => 'Servicios marcados completados'],
                ['label' => 'Pagos por verificar', 'value' => $transfers, 'detail' => 'No contabilizados como ingreso confirmado'],
                ['label' => 'Cancelaciones · 30 días', 'value' => $cancelled, 'detail' => 'Citas canceladas del periodo'],
                ['label' => 'Inasistencias · 30 días', 'value' => $noShows, 'detail' => 'Solo estados no_asistio registrados'],
            ],
            'actions' => array_values(array_filter([
                $pending > 0 ? ['label' => 'Revisar citas pendientes', 'detail' => "Hay {$pending} citas de hoy por confirmar.", 'to' => '/appointments'] : null,
                $transfers > 0 ? ['label' => 'Verificar comprobantes', 'detail' => "Hay {$transfers} pagos pendientes de revisión.", 'to' => '/payments/pending'] : null,
                $noShows > 0 ? ['label' => 'Revisar inasistencias', 'detail' => "Se registraron {$noShows} inasistencias en el periodo. Revisa tus recordatorios y políticas.", 'to' => '/appointments'] : null,
            ])),
        ];
    }
}
