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
 * docker-compose.yml "mongo-test"). Verifica el contrato completo de
 * autorización de routes/web.php: la cadena auth -> verified -> role.custom
 * -> permission.custom, usando los mismos roles/permisos reales que siembra
 * RolePermissionSeeder (no fixtures inventadas), para que un cambio en el
 * seeder o en las rutas que rompa la relación entre ambos lo detecte esta
 * prueba. Cubre justo la categoría de bug ya vista en este proyecto: un rol
 * viendo (o recibiendo 403 en) una pantalla que no le corresponde.
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
        $this->get(route('appointments.index'))->assertRedirect(route('login'));
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('client.barberos.index'))->assertRedirect(route('login'));
        $this->get(route('barber.agenda'))->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_blocked_even_with_the_right_role(): void
    {
        $this->admin->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->admin)->get(route('reports.index'))->assertRedirect(route('verification.notice'));
    }

    /**
     * Grupo compartido administrador+recepcionista (permission.custom:citas.gestionar).
     * Ambos roles lo tienen sembrado por RolePermissionSeeder; barbero/cliente no.
     */
    public function test_appointments_index_is_only_reachable_by_admin_and_recepcion(): void
    {
        $this->actingAs($this->admin)->get(route('appointments.index'))->assertOk();
        $this->actingAs($this->recepcionista)->get(route('appointments.index'))->assertOk();
        $this->actingAs($this->barbero)->get(route('appointments.index'))->assertForbidden();
        $this->actingAs($this->cliente)->get(route('appointments.index'))->assertForbidden();
    }

    /**
     * Mismo grupo de rol, otro permission.custom (clientes.gestionar) — confirma
     * que el chequeo de permiso es real y no solo "cualquiera del grupo pasa".
     */
    public function test_clients_index_is_only_reachable_by_admin_and_recepcion(): void
    {
        $this->actingAs($this->admin)->get(route('clients.index'))->assertOk();
        $this->actingAs($this->recepcionista)->get(route('clients.index'))->assertOk();
        $this->actingAs($this->barbero)->get(route('clients.index'))->assertForbidden();
        $this->actingAs($this->cliente)->get(route('clients.index'))->assertForbidden();
    }

    /**
     * Caso clave: reports.index vive en el grupo role.custom:administrador
     * SOLO (no ",recepcionista"), a diferencia de appointments/clients arriba.
     * Recepcionista comparte el grupo anterior pero debe seguir bloqueada aquí
     * — es justo la distinción que un role.custom mal copiado rompería.
     */
    public function test_reports_index_is_admin_only_recepcion_does_not_leak_in(): void
    {
        $this->actingAs($this->admin)->get(route('reports.index'))->assertOk();
        $this->actingAs($this->recepcionista)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->barbero)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->cliente)->get(route('reports.index'))->assertForbidden();
    }

    public function test_users_index_is_admin_only(): void
    {
        $this->actingAs($this->admin)->get(route('users.index'))->assertOk();
        $this->actingAs($this->recepcionista)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->barbero)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->cliente)->get(route('users.index'))->assertForbidden();
    }

    public function test_client_area_is_only_reachable_by_cliente(): void
    {
        $this->actingAs($this->cliente)->get(route('client.barberos.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('client.barberos.index'))->assertForbidden();
        $this->actingAs($this->recepcionista)->get(route('client.barberos.index'))->assertForbidden();
        $this->actingAs($this->barbero)->get(route('client.barberos.index'))->assertForbidden();
    }

    public function test_barber_area_is_only_reachable_by_barbero(): void
    {
        $this->actingAs($this->barbero)->get(route('barber.agenda'))->assertOk();
        $this->actingAs($this->admin)->get(route('barber.agenda'))->assertForbidden();
        $this->actingAs($this->recepcionista)->get(route('barber.agenda'))->assertForbidden();
        $this->actingAs($this->cliente)->get(route('barber.agenda'))->assertForbidden();
    }

    /**
     * Regresión: email_verified_at faltaba en User::$fillable, así que
     * User::create(['email_verified_at' => now(), ...]) lo descartaba en
     * silencio (sin excepción, sin aviso). Esto rompía el "alta verificada"
     * que varios controladores dan por hecho al crear cuentas manualmente
     * (UserController, Api\UserController, Api\ClientController,
     * Client\ClientController): la cuenta quedaba SIN verificar pese al
     * comentario/intención explícita en el código, y el middleware
     * "verified" la bloqueaba en el primer login.
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

        $this->actingAs($noRole)->get(route('appointments.index'))->assertForbidden();
        $this->actingAs($noRole)->get(route('client.barberos.index'))->assertForbidden();
        $this->actingAs($noRole)->get(route('barber.agenda'))->assertForbidden();
    }
}
