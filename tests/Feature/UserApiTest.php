<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre la gestión de
 * usuarios del sistema del frontend Nuxt (ver frontend-urban/.claude/
 * skills/nuxt-migration-plan/SKILL.md, Fase 9.6). A diferencia de
 * servicios/inventario/pagos/pedidos, Api/User/UserController ya estaba
 * completo (list+filtros, CRUD, sync de perfil Barber/Client por rol,
 * guard de auto-eliminación) desde antes de esta fase — este archivo solo
 * agrega la cobertura de test que le faltaba, no cambia el controlador.
 */
class UserApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Barber::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        // User usa SoftDeletes: query()->delete() solo pone deleted_at, deja
        // el documento físicamente en la colección para siempre (mongo-test
        // es un contenedor persistente). Un email "borrado" así sigue
        // haciendo match en cualquier query cruda que no filtre soft-deletes
        // (p. ej. el presence verifier de 'unique:users,email'), causando
        // que una prueba futura con el mismo email fijo falle con "ya
        // registrado" aunque User::count() lo muestre en 0. forceDelete()
        // sí lo quita de verdad. Ver guardrail correspondiente en
        // .claude/skills/urbanblade-guardrails/SKILL.md.
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function tokenFor(User $user, string $plaintext): string
    {
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
    }

    private function adminUser(string $email = 'admin-users@test.local'): User
    {
        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Admin Users', 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        return $user;
    }

    public function test_admin_can_list_users_with_filters_and_roles_catalog(): void
    {
        $admin = $this->adminUser();
        $token = $this->tokenFor($admin, 'test-plaintext-token-users-list');

        $client = User::create(['name' => 'Cliente Buscable', 'email' => 'cliente-buscable@test.local', 'password' => 'password']);
        $client->forceFill(['email_verified_at' => now(), 'role_id' => [(string) Role::where('name', 'cliente')->firstOrFail()->id]])->save();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/users');
        $response->assertOk();
        $response->assertJsonStructure(['data' => ['*' => ['id', 'name', 'email', 'roles']], 'meta', 'filters', 'roles']);
        $response->assertJsonCount(2, 'data');

        $searched = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/users?q=Buscable');
        $searched->assertJsonCount(1, 'data');
        $searched->assertJsonPath('data.0.email', 'cliente-buscable@test.local');

        $byRole = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/users?role=cliente');
        $byRole->assertJsonCount(1, 'data');
    }

    public function test_creating_a_barbero_user_creates_its_barber_profile(): void
    {
        $admin = $this->adminUser();
        $token = $this->tokenFor($admin, 'test-plaintext-token-users-create-barbero');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Nuevo Barbero', 'email' => 'nuevo-barbero@test.local',
                'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'barbero',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.roles.0', 'barbero');
        $userId = $response->json('data.id');
        $this->assertNotNull(Barber::where('user_id', (string) $userId)->first());
    }

    public function test_admin_can_update_a_user_role_and_optionally_password(): void
    {
        $admin = $this->adminUser();
        $token = $this->tokenFor($admin, 'test-plaintext-token-users-update');

        $target = User::create(['name' => 'Target User', 'email' => 'target-user@test.local', 'password' => 'password']);
        $target->forceFill(['email_verified_at' => now(), 'role_id' => [(string) Role::where('name', 'cliente')->firstOrFail()->id]])->save();
        Client::create(['user_id' => (string) $target->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/users/{$target->id}", [
                'name' => 'Target User', 'email' => 'target-user@test.local', 'role' => 'recepcionista',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.roles.0', 'recepcionista');
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->adminUser();
        $token = $this->tokenFor($admin, 'test-plaintext-token-users-self-delete');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/v1/users/{$admin->id}");

        $response->assertStatus(422);
        $this->assertNotNull(User::find($admin->id));
    }

    public function test_recepcionista_cannot_manage_users(): void
    {
        $role = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $staff = User::create(['name' => 'Recepcion Users', 'email' => 'recepcion-users@test.local', 'password' => 'password']);
        $staff->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $token = $this->tokenFor($staff, 'test-plaintext-token-users-guard');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/users')->assertForbidden();
    }
}
