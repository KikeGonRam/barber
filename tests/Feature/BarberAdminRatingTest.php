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
 * Integración real contra el Mongo local de pruebas. Cubre dos regresiones en
 * BarberAdminController encontradas al construir el reporte mensual de
 * desempeño: (1) calculateRating() promediaba Comment::rating (comentarios
 * del muro social) en vez de BarberReview::rating (reseñas reales de
 * clientes); (2) show()/getPerformanceStats() comparaban 'fecha' (cast
 * 'date', guardado como BSON UTCDateTime) contra strings vía
 * ->toDateString(), lo que en MongoDB nunca hace match — devolvían 0 citas
 * pase lo que pase.
 */
class BarberAdminRatingTest extends TestCase
{
    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin API', 'email' => 'admin-api@test.local', 'password' => 'password']);
        $this->admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $this->token = 'test-plaintext-token';
        MobileApiToken::create([
            'user_id' => (string) $this->admin->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $this->token),
        ]);
    }

    protected function tearDown(): void
    {
        Appointment::query()->delete();
        BarberReview::query()->delete();
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

    public function test_performance_stats_averages_real_barber_reviews_not_social_wall_comments(): void
    {
        $barberUser = User::create(['name' => 'Barbero Reseñado', 'email' => 'barbero-resenas@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Reseñado', 'activo' => true]);

        // Dos clientes distintos: un cliente solo puede reseñar una vez a un
        // mismo barbero (indice unico barber_id+client_id, ver la migracion
        // add_barber_reviews_unique_index).
        $clientA = Client::create(['user_id' => (string) $this->admin->id, 'telefono' => '5550000000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $otherUser = User::create(['name' => 'Otro Cliente', 'email' => 'otro-cliente-resenas@test.local', 'password' => 'password']);
        $clientB = Client::create(['user_id' => (string) $otherUser->id, 'telefono' => '5550000001', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        BarberReview::create(['barber_id' => (string) $barber->id, 'client_id' => (string) $clientA->id, 'rating' => 4]);
        BarberReview::create(['barber_id' => (string) $barber->id, 'client_id' => (string) $clientB->id, 'rating' => 2]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/admin/barbers/'.$barber->slug.'/performance');

        $response->assertOk();
        $this->assertEquals(3.0, $response->json('data.averageRating'));
    }

    public function test_performance_stats_rating_is_zero_when_barber_has_no_reviews(): void
    {
        $barberUser = User::create(['name' => 'Barbero Sin Resenas', 'email' => 'barbero-sin-resenas@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Sin Resenas', 'activo' => true]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/admin/barbers/'.$barber->slug.'/performance');

        $response->assertOk();
        $this->assertEquals(0.0, $response->json('data.averageRating'));
    }

    public function test_show_counts_appointments_completed_this_month(): void
    {
        $barberUser = User::create(['name' => 'Barbero Con Citas', 'email' => 'barbero-citas@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Con Citas', 'activo' => true]);

        Appointment::create([
            'client_id' => (string) Str::uuid(),
            'barber_id' => (string) $barber->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->startOfMonth()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
            'precio_cobrado' => 200,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/admin/barbers/'.$barber->slug);

        $response->assertOk();
        $this->assertSame(1, $response->json('data.appointmentsThisMonth'));
        $this->assertEquals(200.0, $response->json('data.revenueThisMonth'));
    }

    public function test_performance_stats_counts_appointments_this_and_last_month(): void
    {
        $barberUser = User::create(['name' => 'Barbero Comparado', 'email' => 'barbero-comparado@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Comparado', 'activo' => true]);

        Appointment::create([
            'client_id' => (string) Str::uuid(),
            'barber_id' => (string) $barber->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->startOfMonth()->addDays(1)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
        ]);
        Appointment::create([
            'client_id' => (string) Str::uuid(),
            'barber_id' => (string) $barber->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->subMonthNoOverflow()->startOfMonth()->addDays(1)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/admin/barbers/'.$barber->slug.'/performance');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.appointmentsThisMonth'));
        $this->assertSame(1, $response->json('data.appointmentsLastMonth'));
    }
}
