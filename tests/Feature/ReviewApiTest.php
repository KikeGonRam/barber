<?php

namespace Tests\Feature;

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
 * API de administración de reseñas (Fase Muro/Reseñas), puerto de
 * Barber\ReviewController (web): antes no existía ninguna API para esto —
 * bloqueaba tener "Reseñas" en Nuxt.
 */
class ReviewApiTest extends TestCase
{
    private string $adminToken = 'test-review-admin-token';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin Reseñas API', 'email' => 'admin-reviews-api@test.local', 'password' => 'password']);
        $this->admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $this->admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);
    }

    protected function tearDown(): void
    {
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

    private function makeReview(int $rating, ?string $comment = null): BarberReview
    {
        $barberUser = User::create(['name' => 'Barbero X', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero X', 'activo' => true]);

        $clientUser = User::create(['name' => 'Cliente X', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5551234567']);

        return BarberReview::create([
            'barber_id' => (string) $barber->id,
            'client_id' => (string) $client->id,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    public function test_admin_sees_review_list_with_stats(): void
    {
        $this->makeReview(1, 'Muy mal servicio');
        $this->makeReview(5, 'Excelente');

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/reviews');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.bajas', 1)
            ->assertJsonPath('data.0.comment', 'Excelente')
            ->assertJsonStructure(['data' => ['*' => ['id', 'rating', 'comment', 'barber', 'client']], 'meta', 'filters', 'barbers', 'stats']);
    }

    public function test_filters_by_rating(): void
    {
        $this->makeReview(1, 'Mal');
        $this->makeReview(5, 'Bien');

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/reviews?rating=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment', 'Mal');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $recepRole = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $recepUser = User::create(['name' => 'Recep', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $recepUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $recepRole->id]])->save();
        $token = 'test-recep-review-token';
        MobileApiToken::create(['user_id' => (string) $recepUser->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/v1/reviews')->assertStatus(403);
    }
}
