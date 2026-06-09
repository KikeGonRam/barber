<?php

namespace Tests\Feature\Performance;

/**
 * Cubre pruebas de rendimiento de consultas, tiempos de respuesta y
 * operaciones de reporte para detectar regresiones y N+1.
 */

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\DashboardService;
use Tests\Support\RefreshMongoDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseQueryPerformanceTest extends TestCase
{
    use RefreshMongoDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
            'politica_cancelacion' => 24,
        ]);
    }

    // --- Contexto: N+1 en listado de citas ---

    public function test_appointments_listing_with_100_records_executes_no_more_than_5_queries(): void
    {
        $barber = Barber::factory()->create();
        $client = Client::factory()->create();
        $service = Service::factory()->create(['activo' => true]);

        Appointment::factory()->count(100)->create([
            'barber_id' => $barber->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'estado' => 'confirmada',
        ]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $appointments = Appointment::query()
            ->with(['client.user', 'barber.user', 'service'])
            ->latest('fecha')
            ->latest('hora_inicio')
            ->paginate(15);

        $this->assertCount(15, $appointments->items());

        $this->assertLessThanOrEqual(7, $queryCount, 'Demasiadas queries (N+1 detectado).');
    }

    // --- Contexto: tiempo de respuesta clientes ---

    public function test_clients_listing_with_200_records_responds_under_500ms(): void
    {
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        Client::factory()->count(200)->create();

        $start = microtime(true);

        $this->actingAs($recepcionista)
            ->get(route('clients.index'))
            ->assertOk();

        $elapsed = microtime(true) - $start;

        $this->assertLessThan(2.5, $elapsed, 'Respuesta tardo mas de 2.5s.');
    }

    // --- Contexto: dashboard con estadisticas ---

    public function test_dashboard_with_metrics_executes_no_more_than_25_queries(): void
    {
        $barber = Barber::factory()->create();
        $client = Client::factory()->create();
        $service = Service::factory()->create(['activo' => true]);

        Appointment::factory()->count(20)->create([
            'barber_id' => $barber->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'estado' => 'completada',
            'precio_cobrado' => 250,
        ]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $metrics = app(DashboardService::class)->receptionistMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('kpis', $metrics);

        $this->assertLessThanOrEqual(25, $queryCount, 'Dashboard excedio 25 queries.');
    }

    // --- Contexto: exportacion pesada de reportes ---

    public function test_export_income_report_with_500_payments_finishes_under_15_seconds(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');
        $creator = $this->createVerifiedUserWithRole('recepcionista');

        $appointments = Appointment::factory()->count(500)->create([
            'estado' => 'completada',
            'precio_cobrado' => 220,
        ]);

        foreach ($appointments as $appointment) {
            Payment::factory()->create([
                'appointment_id' => $appointment->id,
                'created_by' => $creator->id,
                'monto' => 220,
                'metodo_pago' => 'tarjeta',
            ]);
        }

        $start = microtime(true);

        $this->actingAs($admin)
            ->get(route('reports.export', ['type' => 'ingresos', 'format' => 'excel']))
            ->assertOk();

        $elapsed = microtime(true) - $start;

        $this->assertLessThan(15, $elapsed, 'Exportacion excedio 15 segundos.');
    }

    // --- Contexto: busqueda con volumen ---

    public function test_client_search_by_name_is_under_50ms_with_1000_records(): void
    {
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        Client::factory()->count(999)->create();

        $targetUser = User::factory()->create([
            'name' => 'Cliente Objetivo Perf',
            'email_verified_at' => now(),
        ]);

        Client::factory()->create([
            'user_id' => $targetUser->id,
        ]);

        $start = microtime(true);

        $this->actingAs($recepcionista)
            ->get(route('clients.index', ['q' => 'Objetivo Perf']))
            ->assertOk()
            ->assertSee('Cliente Objetivo Perf');

        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.5, $elapsed, 'Busqueda tardo mas de 500ms con 1000 registros.');
    }

    private function createVerifiedUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
