<?php

namespace Tests\UAT;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * GET /api/v1/admin/reports/comparison y /cohorts: periodo actual contra el anterior y retención por mes de alta.
 * Se usa el periodo «año» para que el resultado no dependa del día del mes en que corra la prueba.
 */
class ReportComparisonApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        Service::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function tokenFor(string $roleName, string $plaintext): string
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName).' Reportes', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plaintext)]);

        return $plaintext;
    }

    private function completedAppointment(Client $client, string $fecha, float $precio): void
    {
        $barber = Barber::first() ?? Barber::create(['user_id' => (string) User::create(['name' => 'Barbero Reportes', 'email' => Str::uuid().'@test.local', 'password' => 'password'])->id, 'nombre' => 'Barbero Reportes', 'activo' => true]);
        $service = Service::first() ?? Service::create(['nombre' => 'Corte Reportes', 'categoria' => 'corte', 'precio' => 150, 'duracion_min' => 30, 'activo' => true]);

        Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => $fecha, 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'completada', 'precio_cobrado' => $precio,
        ]);
    }

    private function client(): Client
    {
        $user = User::create(['name' => 'Cliente Reportes', 'email' => Str::uuid().'@test.local', 'password' => 'password']);

        return Client::create(['user_id' => (string) $user->id, 'telefono' => '5550009876', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
    }

    public function test_comparison_returns_current_previous_and_variation(): void
    {
        $token = $this->tokenFor('administrador', 'tok-admin-comparacion');
        $client = $this->client();
        $this->completedAppointment($client, now()->toDateString(), 300);
        $this->completedAppointment($client, now()->subYear()->toDateString(), 100);

        $response = $this->withToken($token)->getJson('/api/v1/admin/reports/comparison?period=año');

        $response->assertOk()
            ->assertJsonPath('data.period', 'año')
            ->assertJsonPath('data.metricas.ingresos.actual', 300)
            ->assertJsonPath('data.metricas.ingresos.anterior', 100)
            ->assertJsonPath('data.metricas.ingresos.variacion_pct', 200)
            ->assertJsonStructure(['data' => ['actual' => ['desde', 'hasta'], 'anterior' => ['desde', 'hasta'], 'metricas' => ['ingresos', 'citas', 'completadas', 'perdidas', 'clientes_nuevos']]]);
    }

    public function test_variation_is_null_when_the_previous_period_has_no_base(): void
    {
        $token = $this->tokenFor('administrador', 'tok-admin-sin-base');
        $this->completedAppointment($this->client(), now()->toDateString(), 300);

        $this->withToken($token)->getJson('/api/v1/admin/reports/comparison?period=año')
            ->assertOk()
            ->assertJsonPath('data.metricas.ingresos.variacion_pct', null);
    }

    public function test_cohorts_show_retention_by_signup_month(): void
    {
        $token = $this->tokenFor('administrador', 'tok-admin-cohortes');
        $this->completedAppointment($this->client(), now()->toDateString(), 200);

        $response = $this->withToken($token)->getJson('/api/v1/admin/reports/cohorts?months=3');

        $response->assertOk()->assertJsonPath('data.meses', 3);
        $cohortes = $response->json('data.cohortes');
        $this->assertCount(3, $cohortes);
        $actual = end($cohortes);
        $this->assertSame(now()->format('Y-m'), $actual['cohorte']);
        $this->assertGreaterThanOrEqual(1, $actual['clientes']);
        $this->assertSame(100, $actual['retencion'][0]);
    }

    public function test_reports_are_not_available_to_clients(): void
    {
        $token = $this->tokenFor('cliente', 'tok-cliente-reportes');

        $this->withToken($token)->getJson('/api/v1/admin/reports/comparison')->assertStatus(403);
        $this->withToken($token)->getJson('/api/v1/admin/reports/cohorts')->assertStatus(403);
    }
}
