<?php

namespace Tests\UT;

use App\Models\Appointment;
use App\Models\Service;
use App\Services\Dashboard\BusinessInsightService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Lo que dicen tus datos" del dashboard: el servicio premium se compara con el ticket realmente cobrado
 * (antes promediaba también las citas sin cobro y salían cosas como "450 veces el ticket, $450 vs $1"), y la hora
 * pico no cuenta las citas canceladas.
 */
class BusinessInsightServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('dashboard_insights');
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        Service::query()->delete();
        Cache::forget('dashboard_insights');

        parent::tearDown();
    }

    private function service(string $nombre, float $precio): Service
    {
        return Service::create(['nombre' => $nombre, 'precio' => $precio, 'duracion' => 30, 'activo' => true]);
    }

    private function cita(Service $service, string $hora, string $estado = 'completada', ?float $cobrado = null): void
    {
        Appointment::create([
            'client_id' => (string) Str::uuid(),
            'barber_id' => (string) Str::uuid(),
            'service_id' => (string) $service->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => $hora,
            'hora_fin' => $hora,
            'estado' => $estado,
            'precio_cobrado' => $cobrado,
        ]);
    }

    /** @return array<string, array{titulo: string, dato: string, detalle: string}> */
    private function insights(): array
    {
        return collect(app(BusinessInsightService::class)->build())->keyBy('titulo')->all();
    }

    public function test_el_premium_se_compara_con_el_ticket_realmente_cobrado(): void
    {
        $premium = $this->service('Combo Premium', 450);
        $basico = $this->service('Corte', 200);
        $this->cita($premium, '10:00:00', cobrado: 0);
        $this->cita($basico, '11:00:00', cobrado: 200);
        $this->cita($basico, '12:00:00', cobrado: 200);
        $this->cita($basico, '13:00:00', 'pendiente');

        $premiumInsight = $this->insights()['Servicio premium'];

        $this->assertSame('25.0% de las citas', $premiumInsight['dato']);
        $this->assertStringContainsString('2.3 veces el ticket promedio cobrado ($200)', $premiumInsight['detalle']);
        $this->assertStringNotContainsString('$1)', $premiumInsight['detalle']);
    }

    public function test_sin_cobros_no_inventa_una_comparacion(): void
    {
        $premium = $this->service('Combo Premium', 450);
        $this->cita($premium, '10:00:00', 'pendiente');

        $detalle = $this->insights()['Servicio premium']['detalle'];

        $this->assertStringContainsString('Todavía no hay cobros', $detalle);
        $this->assertStringNotContainsString('veces', $detalle);
    }

    public function test_la_hora_pico_no_cuenta_las_canceladas(): void
    {
        $servicio = $this->service('Corte', 200);
        $this->cita($servicio, '09:00:00', 'cancelada');
        $this->cita($servicio, '09:30:00', 'cancelada');
        $this->cita($servicio, '09:45:00', 'cancelada');
        $this->cita($servicio, '10:00:00');
        $this->cita($servicio, '10:30:00', 'confirmada');

        $pico = $this->insights()['Hora pico (30 días)'];

        $this->assertSame('10:00', $pico['dato']);
        $this->assertStringContainsString('2 citas en esa hora', $pico['detalle']);
    }

    public function test_sin_citas_no_hay_hallazgos(): void
    {
        $this->assertSame([], app(BusinessInsightService::class)->build());
    }
}
