<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas (ver .env.testing /
 * docker-compose.yml "mongo-test"). Verifica el contrato de autorización de
 * las páginas Blade que sobreviven al retiro de las páginas ya cubiertas por
 * Nuxt (frontend-urban): la cadena auth -> verified -> role.custom ->
 * permission.custom, usando los mismos roles/permisos reales que siembra
 * RolePermissionSeeder (no fixtures inventadas). Antes cubría también
 * appointments/clients/reports/users/etc. — esas rutas se retiraron junto
 * con sus páginas Blade (Nuxt tiene paridad funcional confirmada), así que
 * esas aserciones se reemplazaron por las de reviews.index/
 * client.membership.card/backups.database.download, las únicas páginas
 * protegidas por rol que quedan en Blade.
 */
class RoleAuthorizationTest extends TestCase
{
    private User $admin;

    private User $recepcionista;

    private User $barbero;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        // Los ids de Role/Permission cambian entre tests (se recrean en cada
        // método); sin esto, el cache en memoria de Spatie podría seguir
        // apuntando a documentos ya borrados del test anterior.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = $this->makeUserWithRole('administrador', 'admin@test.local');
        $this->recepcionista = $this->makeUserWithRole('recepcionista', 'recepcion@test.local');
        $this->barbero = $this->makeUserWithRole('barbero', 'barbero@test.local');
        $this->cliente = $this->makeUserWithRole('cliente', 'cliente@test.local');

        Barber::create(['user_id' => (string) $this->barbero->id, 'nombre' => 'Barbero test', 'activo' => true]);
    }

    protected function tearDown(): void
    {
        Barber::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    // email_verified_at y role_id no están en $fillable de User (ver el
    // comentario ahí sobre por qué el rol vive en role_id): User::create()
    // los ignoraría en silencio, así que se asignan aparte con forceFill().
    private function makeUserWithRole(string $roleName, string $email): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();

        $user = User::create([
            'name' => ucfirst($roleName).' de prueba',
            'email' => $email,
            'password' => 'password',
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
            'role_id' => [(string) $role->id],
        ])->save();

        return $user;
    }

    public function test_guest_is_redirected_to_login_on_protected_routes(): void
    {
        $this->get(route('reviews.index'))->assertRedirect(route('login'));
        $this->get(route('backups.database.download'))->assertRedirect(route('login'));
        $this->get(route('client.membership.card'))->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_blocked_even_with_the_right_role(): void
    {
        $this->admin->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->admin)->get(route('reviews.index'))->assertRedirect(route('verification.notice'));
    }

    /**
     * reviews.index vive en role.custom:administrador +
     * permission.custom:barberos.gestionar — solo admin la tiene sembrada.
     */
    public function test_reviews_index_is_admin_only(): void
    {
        $this->actingAs($this->admin)->get(route('reviews.index'))->assertOk();
        $this->actingAs($this->recepcionista)->get(route('reviews.index'))->assertForbidden();
        $this->actingAs($this->barbero)->get(route('reviews.index'))->assertForbidden();
        $this->actingAs($this->cliente)->get(route('reviews.index'))->assertForbidden();
    }

    public function test_backups_database_download_is_admin_only(): void
    {
        $this->actingAs($this->recepcionista)->get(route('backups.database.download'))->assertForbidden();
        $this->actingAs($this->barbero)->get(route('backups.database.download'))->assertForbidden();
        $this->actingAs($this->cliente)->get(route('backups.database.download'))->assertForbidden();
    }

    public function test_membership_card_is_only_reachable_by_cliente(): void
    {
        $this->actingAs($this->admin)->get(route('client.membership.card'))->assertForbidden();
        $this->actingAs($this->recepcionista)->get(route('client.membership.card'))->assertForbidden();
        $this->actingAs($this->barbero)->get(route('client.membership.card'))->assertForbidden();
    }

    /**
     * Regresión: email_verified_at faltaba en User::$fillable, así que
     * User::create(['email_verified_at' => now(), ...]) lo descartaba en
     * silencio (sin excepción, sin aviso). Esto rompía el "alta verificada"
     * que varios controladores dan por hecho al crear cuentas manualmente.
     */
    public function test_creating_a_user_with_email_verified_at_via_mass_assignment_persists_it(): void
    {
        $user = User::create([
            'name' => 'Alta manual',
            'email' => 'alta-manual@test.local',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /**
     * hasRoleName() (usado por EnsureUserHasRole) resuelve roles desde
     * role_id en el propio documento del usuario — no desde una relación
     * MorphToMany. Un usuario sin ningún role_id no debe colar en ninguna
     * ruta protegida por rol.
     */
    public function test_user_without_any_role_is_forbidden_everywhere(): void
    {
        $noRole = User::create([
            'name' => 'Sin rol',
            'email' => 'sinrol@test.local',
            'password' => 'password',
        ]);
        $noRole->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($noRole)->get(route('reviews.index'))->assertForbidden();
        $this->actingAs($noRole)->get(route('backups.database.download'))->assertForbidden();
        $this->actingAs($noRole)->get(route('client.membership.card'))->assertForbidden();
    }
}
