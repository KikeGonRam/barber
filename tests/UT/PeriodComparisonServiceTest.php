<?php

namespace Tests\UT;

use App\Services\Analytics\PeriodComparisonService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Ventanas del «periodo anterior»: el mismo tramo del periodo calendario previo, no el periodo previo completo.
 * Lógica de fechas pura: no necesita base de datos.
 */
class PeriodComparisonServiceTest extends TestCase
{
    private PeriodComparisonService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PeriodComparisonService;
    }

    public function test_month_compares_the_same_stretch_of_the_previous_month(): void
    {
        $w = $this->service->windows('mes', Carbon::parse('2026-10-12 15:00:00'));

        $this->assertSame('2026-10-01', $w['actual']['desde']->toDateString());
        $this->assertSame('2026-09-01', $w['anterior']['desde']->toDateString());
        $this->assertSame('2026-09-12', $w['anterior']['hasta']->toDateString());
    }

    public function test_the_previous_window_never_reaches_into_the_current_period(): void
    {
        // 31 de octubre: el tramo equivalente de septiembre (30 días) se acota al 30 de septiembre.
        $w = $this->service->windows('mes', Carbon::parse('2026-10-31 20:00:00'));

        $this->assertSame('2026-09-30', $w['anterior']['hasta']->toDateString());
        $this->assertTrue($w['anterior']['hasta']->lt($w['actual']['desde']));
    }

    public function test_day_week_quarter_and_year_windows(): void
    {
        $ahora = Carbon::parse('2026-10-29 10:00:00'); // jueves

        $dia = $this->service->windows('dia', $ahora);
        $this->assertSame('2026-10-28', $dia['anterior']['desde']->toDateString());

        $semana = $this->service->windows('semana', $ahora);
        // El inicio de semana depende de la configuración regional de la app: se compara contra el mismo cálculo.
        $inicioSemana = $ahora->copy()->startOfWeek();
        $this->assertSame($inicioSemana->toDateString(), $semana['actual']['desde']->toDateString());
        $this->assertSame($inicioSemana->copy()->subWeek()->toDateString(), $semana['anterior']['desde']->toDateString());
        $this->assertTrue($semana['anterior']['hasta']->lt($semana['actual']['desde']));

        $trimestre = $this->service->windows('trimestre', $ahora);
        $this->assertSame('2026-10-01', $trimestre['actual']['desde']->toDateString());
        $this->assertSame('2026-07-01', $trimestre['anterior']['desde']->toDateString());

        $anio = $this->service->windows('año', $ahora);
        $this->assertSame('2025-01-01', $anio['anterior']['desde']->toDateString());
    }

    public function test_unknown_period_falls_back_to_month(): void
    {
        $w = $this->service->windows('quincena', Carbon::parse('2026-10-12 15:00:00'));

        $this->assertSame('2026-10-01', $w['actual']['desde']->toDateString());
    }

    public function test_variation_is_null_without_a_base_and_rounded_otherwise(): void
    {
        $this->assertNull($this->service->variation(500.0, 0.0));
        $this->assertSame(25.0, $this->service->variation(500.0, 400.0));
        $this->assertSame(-33.3, $this->service->variation(200.0, 300.0));
        $this->assertSame(-100.0, $this->service->variation(0.0, 50.0));
    }
}
