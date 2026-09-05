<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\User;
use App\Services\Barber\BarberPerformanceService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre el reporte
 * mensual de desempeño de barberos: reconocimiento del mejor mes y detección
 * de caídas fuertes vs. el mes anterior (BarberPerformanceService), la pieza
 * que conecta "rendimiento de barberos" (antes solo un reporte pasivo) con
 * una acción real (BarberMonthlyPerformanceCommand la usa para notificar).
 */
class BarberPerformanceServiceIntegrationTest extends TestCase
{
    private BarberPerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BarberPerformanceService;
    }

    protected function tearDown(): void
    {
        Appointment::query()->delete();
        Barber::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    private function makeBarber(string $name): Barber
    {
        $user = User::create(['name' => $name, 'email' => Str::uuid().'@test.local', 'password' => 'password']);

        return Barber::create(['user_id' => (string) $user->id, 'nombre' => $name, 'activo' => true]);
    }

    private function makeCompletedAppointments(Barber $barber, Carbon $inMonth, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Appointment::create([
                'client_id' => (string) Str::uuid(),
                'barber_id' => (string) $barber->id,
                'service_id' => (string) Str::uuid(),
                'fecha' => $inMonth->copy()->startOfMonth()->addDays($i % 25)->format('Y-m-d'),
                'hora_inicio' => '09:00:00',
                'hora_fin' => '09:30:00',
                'estado' => 'completada',
            ]);
        }
    }

    public function test_identifies_the_barber_with_the_most_completed_appointments_as_top_performer(): void
    {
        $reference = Carbon::create(2026, 3, 15);
        $closedMonth = $reference->copy()->subMonthNoOverflow();

        $top = $this->makeBarber('Barbero Top');
        $this->makeCompletedAppointments($top, $closedMonth, 10);

        $other = $this->makeBarber('Barbero Regular');
        $this->makeCompletedAppointments($other, $closedMonth, 3);

        $report = $this->service->monthlyReport($reference);

        $this->assertSame('Barbero Top', $report['top_performer']['nombre']);
        $this->assertSame(10, $report['top_performer']['citas']);
    }

    public function test_flags_a_barber_with_a_strong_drop_vs_the_prior_month(): void
    {
        $reference = Carbon::create(2026, 3, 15);
        $closedMonth = $reference->copy()->subMonthNoOverflow();
        $priorMonth = $reference->copy()->subMonthsNoOverflow(2);

        $barber = $this->makeBarber('Barbero En Caida');
        $this->makeCompletedAppointments($barber, $priorMonth, 10);
        $this->makeCompletedAppointments($barber, $closedMonth, 4); // -60%

        $report = $this->service->monthlyReport($reference);

        $this->assertCount(1, $report['underperformers']);
        $this->assertSame('Barbero En Caida', $report['underperformers'][0]['nombre']);
        $this->assertSame(10, $report['underperformers'][0]['citas_mes_anterior']);
        $this->assertSame(4, $report['underperformers'][0]['citas_mes']);
        $this->assertSame(60, $report['underperformers'][0]['caida_pct']);
    }

    public function test_does_not_flag_a_moderate_drop_below_the_threshold(): void
    {
        $reference = Carbon::create(2026, 3, 15);
        $closedMonth = $reference->copy()->subMonthNoOverflow();
        $priorMonth = $reference->copy()->subMonthsNoOverflow(2);

        $barber = $this->makeBarber('Barbero Estable');
        $this->makeCompletedAppointments($barber, $priorMonth, 10);
        $this->makeCompletedAppointments($barber, $closedMonth, 7); // -30%, bajo el umbral de 40%

        $report = $this->service->monthlyReport($reference);

        $this->assertEmpty($report['underperformers']);
    }

    public function test_ignores_a_drop_when_the_prior_month_baseline_is_too_small(): void
    {
        $reference = Carbon::create(2026, 3, 15);
        $closedMonth = $reference->copy()->subMonthNoOverflow();
        $priorMonth = $reference->copy()->subMonthsNoOverflow(2);

        // Solo 3 citas el mes anterior (bajo MIN_BASELINE_APPOINTMENTS=5): una
        // caída del 100% aquí es ruido, no una señal real de problema.
        $barber = $this->makeBarber('Barbero Nuevo');
        $this->makeCompletedAppointments($barber, $priorMonth, 3);
        $this->makeCompletedAppointments($barber, $closedMonth, 0);

        $report = $this->service->monthlyReport($reference);

        $this->assertEmpty($report['underperformers']);
    }

    public function test_returns_null_top_performer_when_nobody_completed_appointments(): void
    {
        $reference = Carbon::create(2026, 3, 15);
        $this->makeBarber('Barbero Sin Citas');

        $report = $this->service->monthlyReport($reference);

        $this->assertNull($report['top_performer']);
        $this->assertEmpty($report['underperformers']);
    }
}
