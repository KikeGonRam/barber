<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas para el comando
 * `loyalty:apply-inactivity`: cubre que el --dry-run no escribe nada y que
 * la ejecución real baja de nivel / expira puntos usando la última cita
 * completada de cada cliente (o su fecha de alta si nunca tuvo una).
 */
class ApplyLoyaltyInactivityCommandTest extends TestCase
{
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

    private function makeCompletedAppointment(Client $client, int $daysAgo): void
    {
        $barber = Barber::create(['nombre' => 'Barbero de prueba', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->subDays($daysAgo)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
        ]);
    }

    public function test_dry_run_reports_changes_without_writing(): void
    {
        $client = $this->makeClient(['nivel' => 'vip', 'puntos' => 50, 'total_citas' => 10]);
        $this->makeCompletedAppointment($client, 200);

        $this->artisan('loyalty:apply-inactivity', ['--dry-run' => true])
            ->assertExitCode(0);

        $client->refresh();

        $this->assertSame('vip', $client->nivel);
        $this->assertSame(50, $client->puntos);
    }

    public function test_downgrades_level_for_client_inactive_past_threshold(): void
    {
        $client = $this->makeClient(['nivel' => 'vip', 'puntos' => 30, 'total_citas' => 10]);
        $this->makeCompletedAppointment($client, 200);

        $this->artisan('loyalty:apply-inactivity')->assertExitCode(0);

        $client->refresh();

        $this->assertSame('regular', $client->nivel);
    }

    public function test_leaves_active_clients_untouched(): void
    {
        $client = $this->makeClient(['nivel' => 'vip', 'puntos' => 30, 'total_citas' => 10]);
        $this->makeCompletedAppointment($client, 5);

        $this->artisan('loyalty:apply-inactivity')->assertExitCode(0);

        $client->refresh();

        $this->assertSame('vip', $client->nivel);
        $this->assertSame(30, $client->puntos);
    }

    public function test_uses_client_creation_date_when_there_is_no_completed_appointment(): void
    {
        $client = $this->makeClient(['nivel' => 'regular', 'puntos' => 10, 'total_citas' => 5]);
        $client->created_at = now()->subDays(400);
        $client->save();

        $this->artisan('loyalty:apply-inactivity')->assertExitCode(0);

        $client->refresh();

        $this->assertSame('nuevo', $client->nivel);
        $this->assertSame(0, $client->puntos);
    }
}
