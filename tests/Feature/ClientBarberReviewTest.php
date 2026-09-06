<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Regresión: antes solo
 * CatalogController (API móvil) sincronizaba calificacion_promedio/
 * total_resenas del barbero al crear una reseña. Client\ClientBarberController
 * (web) tenía el mismo bug hasta que ambos empezaron a delegar en
 * BarberReviewService — esa página web se retiró (Nuxt tiene "Nuestros
 * Barberos" con paridad funcional confirmada, ver Fase 9.8), así que este
 * archivo ya solo cubre la vía API, que sigue siendo el contrato real.
 */
class ClientBarberReviewTest extends TestCase
{
    private User $clientUser;

    private Client $client;

    private Barber $barber;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $this->clientUser = User::create(['name' => 'Cliente Web', 'email' => 'cliente-review-web@test.local', 'password' => 'password']);
        $this->clientUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $this->client = Client::create(['user_id' => (string) $this->clientUser->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $barberUser = User::create(['name' => 'Barbero Web', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $this->barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Web', 'activo' => true]);

        Appointment::create([
            'client_id' => (string) $this->client->id,
            'barber_id' => (string) $this->barber->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->subDay()->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
        ]);
    }

    protected function tearDown(): void
    {
        BarberReview::query()->delete();
        Appointment::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_api_review_submission_syncs_barber_denormalized_stats(): void
    {
        $token = 'test-plaintext-token-review';
        MobileApiToken::create([
            'user_id' => (string) $this->clientUser->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/barbers/'.$this->barber->slug.'/review', [
                'rating' => 2,
                'comment' => 'Regular',
            ]);

        $response->assertCreated();

        $freshBarber = Barber::find($this->barber->id);
        $this->assertEquals(2.0, (float) $freshBarber->calificacion_promedio);
        $this->assertSame(1, $freshBarber->total_resenas);
    }
}
