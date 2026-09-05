<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\Client;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre la pantalla de
 * administración de reseñas (antes inexistente: un admin avisado de una
 * reseña de 1-2 estrellas no tenía dónde verla dentro del sistema).
 */
class ReviewControllerTest extends TestCase
{
    private User $admin;

    private User $recepcionista;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin Reseñas', 'email' => 'admin-review-ctrl@test.local', 'password' => 'password']);
        $this->admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $adminRole->id]])->save();

        $recepcionRole = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $this->recepcionista = User::create(['name' => 'Recepcion', 'email' => 'recepcion-review-ctrl@test.local', 'password' => 'password']);
        $this->recepcionista->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $recepcionRole->id]])->save();
    }

    protected function tearDown(): void
    {
        BarberReview::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
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
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        return BarberReview::create([
            'barber_id' => (string) $barber->id,
            'client_id' => (string) $client->id,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    public function test_admin_can_see_the_review_list_with_the_comment_and_reviewer_name(): void
    {
        $this->makeReview(1, 'Muy mal servicio');

        $response = $this->actingAs($this->admin)->get(route('reviews.index'));

        $response->assertOk();
        $response->assertSee('Muy mal servicio');
        $response->assertSee('Cliente X');
        $response->assertSee('Barbero X');
    }

    public function test_stats_count_low_ratings_separately(): void
    {
        $this->makeReview(1);
        $this->makeReview(5);

        $response = $this->actingAs($this->admin)->get(route('reviews.index'));

        $response->assertOk();
        $response->assertViewHas('stats', function ($stats) {
            return $stats['total'] === 2 && $stats['bajas'] === 1;
        });
    }

    public function test_recepcion_is_forbidden_from_the_reviews_screen(): void
    {
        $this->actingAs($this->recepcionista)->get(route('reviews.index'))->assertForbidden();
    }
}
