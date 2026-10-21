<?php

namespace App\Services\Dashboard;

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Facades\Cache;

/**
 * Hallazgos de negocio de "Lo que dicen tus datos" en el dashboard del administrador y del ingeniero:
 * servicio premium, cancelaciones del mes y hora pico. Cacheado 10 min porque agrega sobre todas las citas.
 */
class BusinessInsightService
{
    private const CACHE_KEY = 'dashboard_insights';

    private const CACHE_SECONDS = 600;

    /**
     * @return array<int, array{titulo: string, dato: string, detalle: string}>
     */
    public function build(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            return array_values(array_filter([
                $this->premium(),
                $this->cancellations(),
                $this->peakHour(),
            ]));
        });
    }

    /** El servicio más caro, qué parte de las citas es y cuántas veces supera el ticket real cobrado. */
    private function premium(): ?array
    {
        $premium = Service::where('activo', true)->orderByDesc('precio')->first();
        $totalCitas = Appointment::count();
        if (! $premium || $totalCitas === 0) {
            return null;
        }

        $premiumCitas = Appointment::where('service_id', (string) $premium->id)->count();
        // Solo cuentan las citas con cobro: promediar todas, con las que no se cobraron, daba tickets de $1.
        $avgTicket = (float) Appointment::where('precio_cobrado', '>', 0)->avg('precio_cobrado');
        $price = (float) $premium->precio;

        $detalle = $avgTicket > 0
            ? sprintf(
                '«%s» cuesta $%s, %s veces el ticket promedio cobrado ($%s): buen candidato para ofrecerlo como mejora.',
                $premium->nombre, number_format($price, 0), number_format($price / $avgTicket, 1), number_format($avgTicket, 0)
            )
            : sprintf('«%s» es tu servicio de mayor precio ($%s). Todavía no hay cobros para compararlo con el ticket promedio.', $premium->nombre, number_format($price, 0));

        return [
            'titulo' => 'Servicio premium',
            'dato' => sprintf('%.1f%% de las citas', $premiumCitas / $totalCitas * 100),
            'detalle' => $detalle,
        ];
    }

    /** Tasa de cancelación de este mes frente al anterior; solo con muestra suficiente en ambos. */
    private function cancellations(): ?array
    {
        $iniMes = now()->startOfMonth();
        $iniPrev = now()->subMonthNoOverflow()->startOfMonth();
        $finPrev = $iniMes->copy()->subSecond();
        $mesTot = Appointment::where('fecha', '>=', $iniMes)->count();
        $mesCan = Appointment::where('fecha', '>=', $iniMes)->where('estado', 'cancelada')->count();
        $prevTot = Appointment::whereBetween('fecha', [$iniPrev, $finPrev])->count();
        $prevCan = Appointment::whereBetween('fecha', [$iniPrev, $finPrev])->where('estado', 'cancelada')->count();
        if ($mesTot < 10 || $prevTot < 10) {
            return null;
        }

        $tasaMes = $mesCan / $mesTot * 100;
        $tasaPrev = $prevCan / $prevTot * 100;

        return [
            'titulo' => 'Cancelaciones del mes',
            'dato' => sprintf('%.1f%%', $tasaMes),
            'detalle' => sprintf(
                '%s frente a %.1f%% el mes pasado (%d de %d citas).',
                $tasaMes > $tasaPrev ? 'Subió' : 'Bajó', $tasaPrev, $mesCan, $mesTot
            ),
        ];
    }

    /** La hora con más citas en 30 días, sin contar las canceladas (igual que el mapa de calor de horas pico). */
    private function peakHour(): ?array
    {
        $horas = Appointment::where('fecha', '>=', now()->subDays(30))
            ->where('estado', '!=', 'cancelada')
            ->pluck('hora_inicio')
            ->map(fn ($h) => substr((string) $h, 0, 2))
            ->countBy();
        if ($horas->isEmpty()) {
            return null;
        }

        $pico = (string) $horas->sortDesc()->keys()->first();

        return [
            'titulo' => 'Hora pico (30 días)',
            'dato' => "{$pico}:00",
            'detalle' => sprintf(
                '%d citas en esa hora, sin contar canceladas. Refuerza ahí a los barberos y promociona las horas más tranquilas; el mapa de horas pico está en Reportes.',
                $horas[$pico]
            ),
        ];
    }
}
