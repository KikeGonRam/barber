<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre el catálogo
 * de servicios del frontend Nuxt (ver frontend-urban/.claude/skills/
 * nuxt-migration-plan/SKILL.md, Fase 9.6) — puerto de
 * Service\ServiceController (web), enriquecido en esta misma fase con
 * el filtro `q` (categoria/activo ya existían) e `imagen_url`.
 */
class ServiceManagementApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Service::query()->delete();
        MobileApiToken::query()->delete();
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

    private function roleUser(string $roleName, string $email): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName).' ServiceMgmt', 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        return $user;
    }

    public function test_admin_can_list_filter_and_manage_services(): void
    {
        $admin = $this->roleUser('administrador', 'admin-services@test.local');
        Service::create(['nombre' => 'Corte Clásico', 'categoria' => 'corte', 'precio' => 150, 'duracion_min' => 30, 'activo' => true]);
        Service::create(['nombre' => 'Barba', 'categoria' => 'barba', 'precio' => 80, 'duracion_min' => 20, 'activo' => false]);

        $token = $this->tokenFor($admin, 'test-plaintext-token-services-list');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/services/manage');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure(['data' => ['*' => ['id', 'nombre', 'categoria', 'precio', 'duracion_min', 'imagen', 'imagen_url', 'descripcion', 'activo']], 'meta', 'categories']);

        $filtered = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/services/manage?q=barba');
        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.nombre', 'Barba');

        $activeOnly = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/services/manage?activo=1');
        $activeOnly->assertJsonCount(1, 'data');
        $activeOnly->assertJsonPath('data.0.nombre', 'Corte Clásico');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/services/manage', ['nombre' => 'Diseño', 'categoria' => 'corte', 'precio' => 200, 'duracion_min' => 45]);
        $created->assertCreated();
        $serviceId = $created->json('data.id');
        // Service usa HasSlug -> getRouteKeyName() = 'slug': PUT/DELETE
        // ligan por slug, no por id (ver guardrail #20 de este repo).
        $serviceSlug = $created->json('data.slug');
        $this->assertNotEmpty($serviceSlug);

        $updated = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/services/manage/{$serviceSlug}", ['nombre' => 'Diseño Premium', 'categoria' => 'corte', 'precio' => 220, 'duracion_min' => 45]);
        $updated->assertOk();
        $updated->assertJsonPath('data.nombre', 'Diseño Premium');

        $deleted = $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/v1/services/manage/{$serviceSlug}");
        $deleted->assertOk();
        $this->assertNull(Service::find($serviceId));
    }

    public function test_recepcionista_cannot_manage_services(): void
    {
        $staff = $this->roleUser('recepcionista', 'recepcion-services@test.local');
        $token = $this->tokenFor($staff, 'test-plaintext-token-services-guard');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/services/manage')->assertForbidden();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/services/manage', ['nombre' => 'X', 'categoria' => 'corte', 'precio' => 10, 'duracion_min' => 10])
            ->assertForbidden();
    }
}
