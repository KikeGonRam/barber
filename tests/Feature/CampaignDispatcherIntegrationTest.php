<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Services\Campaign\CampaignDispatcher;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre que
 * CampaignDispatcher pueda targetear el segmento 'inactive' (clientes en
 * riesgo, 30+ días sin cita) además de los segmentos por nivel de lealtad ya
 * existentes — la pieza que conecta el cálculo de "clientes en riesgo" con
 * poder realmente contactarlos.
 */
class CampaignDispatcherIntegrationTest extends TestCase
{
    private CampaignDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = app(CampaignDispatcher::class);
    }

    protected function tearDown(): void
    {
        Appointment::query()->delete();
        Client::query()->delete();
        Barber::query()->delete();
        Service::query()->delete();

        parent::tearDown();
    }

    private function makeClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'user_id' => (string) Str::uuid(),
            'telefono' => '5551234567',
            'nivel' => 'nuevo',
            'puntos' => 0,
            'total_citas' => 0,
        ], $overrides));
    }

    private function makeOldAppointment(Client $client, int $daysAgo): void
    {
        $barber = Barber::create(['nombre' => 'Barbero de prueba', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->subDays($daysAgo)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
        ]);

        // Cliente creado con created_at "ahora" por defecto haria que el
        // cliente cuente como "nuevo" (< 14 dias) sin importar la cita —
        // se fuerza una fecha de alta vieja para simular un cliente real
        // que ya paso la ventana de "nuevo".
        $client->created_at = now()->subDays($daysAgo + 60);
        $client->save();

        $appointment->fresh();
    }

    public function test_audience_user_ids_includes_only_clients_inactive_thirty_plus_days(): void
    {
        $inactivo = $this->makeClient(['user_id' => 'user-inactivo']);
        $this->makeOldAppointment($inactivo, 45);

        $activo = $this->makeClient(['user_id' => 'user-activo']);
        $this->makeOldAppointment($activo, 5);

        $userIds = $this->dispatcher->audienceUserIds('inactive');

        $this->assertTrue($userIds->contains('user-inactivo'));
        $this->assertFalse($userIds->contains('user-activo'));
    }

    public function test_segment_counts_includes_inactive_bucket(): void
    {
        $inactivo = $this->makeClient(['user_id' => 'user-inactivo-2']);
        $this->makeOldAppointment($inactivo, 60);

        $counts = $this->dispatcher->segmentCounts();

        $this->assertArrayHasKey('inactive', $counts);
        $this->assertGreaterThanOrEqual(1, $counts['inactive']);
    }

    public function test_audience_user_ids_still_works_for_loyalty_level_segments(): void
    {
        $this->makeClient(['user_id' => 'user-vip', 'nivel' => 'vip']);
        $this->makeClient(['user_id' => 'user-regular', 'nivel' => 'regular']);

        $userIds = $this->dispatcher->audienceUserIds('vip');

        $this->assertTrue($userIds->contains('user-vip'));
        $this->assertFalse($userIds->contains('user-regular'));
    }
}
