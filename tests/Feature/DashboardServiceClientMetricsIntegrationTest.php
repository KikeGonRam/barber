<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas (ver .env.testing /
 * docker-compose.yml "mongo-test"). Cubre clientMetrics() de
 * DashboardService — el único de los cuatro dashboards que NO usa
 * Cache::remember() (se recalcula en cada request), así que cada test ve
 * el efecto inmediato de los datos que crea. DashboardService es enorme
 * (4 dashboards, múltiples agregaciones); este archivo se enfoca en
 * clientMetrics() por su lógica de negocio más densa: de dónde sale
 * "citas completadas" (el campo persistido vs. contar en vivo) y la
 * progresión de lealtad.
 */
class DashboardServiceClientMetricsIntegrationTest extends TestCase
{
    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DashboardService::class);
    }

    protected function tearDown(): void
    {
        Appointment::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    private function makeAppointment(string $clientId, string $estado, array $overrides = []): Appointment
    {
        return Appointment::create(array_merge([
            'client_id' => $clientId,
            'fecha' => Carbon::today()->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => $estado,
        ], $overrides));
    }

    public function test_completed_appointments_trusts_the_persisted_total_citas_over_a_live_count(): void
    {
        // total_citas queda "adelantado" respecto a las citas reales marcadas
        // completada (puede pasar si el conteo se desincroniza) — clientMetrics()
        // debe confiar en el campo persistido, no recontar en vivo.
        $client = Client::create(['telefono' => '5550001111', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 5]);
        $this->makeAppointment((string) $client->id, 'completada');
        $this->makeAppointment((string) $client->id, 'completada');
        $this->makeAppointment((string) $client->id, 'cancelada');

        $data = $this->service->clientMetrics((string) $client->id);

        $this->assertSame(3, $data['kpis']['total_appointments']);
        $this->assertSame(5, $data['kpis']['completed_appointments']);
        // completedForRate = min(5, 3) = 3 -> 3/3 = 100%
        $this->assertSame(100.0, $data['kpis']['completion_rate']);
        $this->assertSame(round(1 / 3 * 100, 1), $data['kpis']['cancellation_rate']);
    }

    public function test_completed_appointments_falls_back_to_a_live_count_when_total_citas_is_unset(): void
    {
        $client = Client::create(['telefono' => '5550002222', 'nivel' => 'nuevo', 'puntos' => 0]);
        $this->makeAppointment((string) $client->id, 'completada');
        $this->makeAppointment((string) $client->id, 'completada');
        $this->makeAppointment((string) $client->id, 'pendiente');

        $data = $this->service->clientMetrics((string) $client->id);

        $this->assertSame(2, $data['kpis']['completed_appointments']);
    }

    public function test_next_appointment_excludes_terminal_states_and_picks_the_soonest(): void
    {
        $client = Client::create(['telefono' => '5550003333', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $this->makeAppointment((string) $client->id, 'completada', ['fecha' => Carbon::today()->addDays(1)->format('Y-m-d')]);
        $soonest = $this->makeAppointment((string) $client->id, 'confirmada', ['fecha' => Carbon::today()->addDays(2)->format('Y-m-d')]);
        $this->makeAppointment((string) $client->id, 'pendiente', ['fecha' => Carbon::today()->addDays(5)->format('Y-m-d')]);

        $data = $this->service->clientMetrics((string) $client->id);

        $this->assertNotNull($data['next_appointment']);
        $this->assertSame((string) $soonest->id, (string) $data['next_appointment']['id']);
        $this->assertSame('confirmada', $data['next_appointment']['estado']);
    }

    public function test_next_appointment_is_null_when_only_terminal_appointments_exist(): void
    {
        $client = Client::create(['telefono' => '5550004444', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $this->makeAppointment((string) $client->id, 'completada', ['fecha' => Carbon::today()->addDays(1)->format('Y-m-d')]);
        $this->makeAppointment((string) $client->id, 'cancelada', ['fecha' => Carbon::today()->addDays(2)->format('Y-m-d')]);

        $data = $this->service->clientMetrics((string) $client->id);

        $this->assertNull($data['next_appointment']);
    }

    public function test_favorite_barber_is_the_one_with_the_most_appointments(): void
    {
        $client = Client::create(['telefono' => '5550005555', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $userFavorito = User::create(['name' => 'Barbero Favorito', 'email' => 'favorito@test.local', 'password' => 'password']);
        $barberFavorito = Barber::create(['user_id' => (string) $userFavorito->id, 'nombre' => 'Favorito', 'activo' => true]);
        $barberOcasional = Barber::create(['nombre' => 'Ocasional', 'activo' => true]);

        $this->makeAppointment((string) $client->id, 'completada', ['barber_id' => (string) $barberFavorito->id]);
        $this->makeAppointment((string) $client->id, 'completada', ['barber_id' => (string) $barberFavorito->id]);
        $this->makeAppointment((string) $client->id, 'completada', ['barber_id' => (string) $barberFavorito->id]);
        $this->makeAppointment((string) $client->id, 'completada', ['barber_id' => (string) $barberOcasional->id]);

        $data = $this->service->clientMetrics((string) $client->id);

        $this->assertSame('Barbero Favorito', $data['kpis']['favorite_barber']);
    }

    public function test_loyalty_progression_reflects_the_next_level_and_remaining_visits(): void
    {
        // 'regular' (>=5 citas) -> siguiente nivel 'vip' (10 citas).
        $client = Client::create([
            'telefono' => '5550006666',
            'nivel' => 'regular',
            'puntos' => 30,
            'total_citas' => 6,
        ]);

        $data = $this->service->clientMetrics((string) $client->id);

        $this->assertSame('regular', $data['loyalty']['nivel']);
        $this->assertSame(30, $data['loyalty']['puntos']);
        $this->assertSame('vip', $data['loyalty']['next_nivel']);
        $this->assertSame(10, $data['loyalty']['citas_next_nivel']);
        $this->assertSame(4, $data['loyalty']['citas_faltan']); // 10 - 6
        $this->assertSame(60.0, $data['loyalty']['progress_pct']); // 6/10 * 100
    }

    public function test_loyalty_progression_caps_progress_at_the_max_level(): void
    {
        $client = Client::create([
            'telefono' => '5550007777',
            'nivel' => 'leyenda',
            'puntos' => 500,
            'total_citas' => 25,
        ]);

        $data = $this->service->clientMetrics((string) $client->id);

        $this->assertNull($data['loyalty']['next_nivel']);
        $this->assertSame(0, $data['loyalty']['citas_faltan']);
        $this->assertSame(100, $data['loyalty']['progress_pct']);
    }
}
