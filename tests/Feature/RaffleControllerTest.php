<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Permission;
use App\Models\RaffleResult;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre la pantalla de
 * administración de sorteos (antes inexistente: no había forma de ver el
 * historial de ganadores ni si ya reclamaron su premio).
 */
class RaffleControllerTest extends TestCase
{
    private User $admin;

    private User $recepcionista;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin Sorteos', 'email' => 'admin-sorteos@test.local', 'password' => 'password']);
        $this->admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $adminRole->id]])->save();

        $recepcionRole = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $this->recepcionista = User::create(['name' => 'Recepcion Sorteos', 'email' => 'recepcion-sorteos@test.local', 'password' => 'password']);
        $this->recepcionista->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $recepcionRole->id]])->save();
    }

    protected function tearDown(): void
    {
        RaffleResult::query()->delete();
        Client::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_admin_can_see_raffle_history_with_redemption_status(): void
    {
        $winnerUser = User::create(['name' => 'Ganador X', 'email' => 'ganador-x@test.local', 'password' => 'password']);
        $winner = Client::create(['user_id' => (string) $winnerUser->id, 'telefono' => '5551234567', 'nivel' => 'vip', 'puntos' => 0, 'total_citas' => 12]);

        RaffleResult::create([
            'client_id' => (string) $winner->id,
            'mes' => '2026-01',
            'premio' => 'Corte premium gratis',
            'nivel_ganador' => 'vip',
            'vence_en' => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->admin)->get(route('raffles.index'));

        $response->assertOk();
        $response->assertSee('Ganador X');
        $response->assertSee('2026-01');
        $response->assertSee('Vigente hasta');
    }

    public function test_recepcion_is_forbidden_from_the_raffles_screen(): void
    {
        $this->actingAs($this->recepcionista)->get(route('raffles.index'))->assertForbidden();
    }
}
