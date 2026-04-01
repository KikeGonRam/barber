<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Refrescar permisos y roles
        if (class_exists(RolePermissionSeeder::class)) {
            $this->seed(RolePermissionSeeder::class);
        } else {
            Role::firstOrCreate(['name' => 'administrador']);
            Role::firstOrCreate(['name' => 'recepcionista']);
            Role::firstOrCreate(['name' => 'barbero']);
            Role::firstOrCreate(['name' => 'cliente']);
        }
    }

    public function test_admin_can_create_and_edit_inventory()
    {

        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        // Asignar todos los permisos al admin explícitamente
        if (method_exists($admin, 'givePermissionTo')) {
            $admin->givePermissionTo(Permission::all());
        }

        $this->actingAs($admin)
            ->post(route('almacen.store'), [
                'name' => 'Producto Test',
                'quantity' => 2,
                'min_stock' => 5,
                'price' => 10.5,
                'description' => 'Desc',
                'active' => true,
            ])->assertRedirect(route('almacen.index'));

        $inventory = Inventory::first();
        $this->assertEquals('Producto Test', $inventory->name);

        $this->actingAs($admin)
            ->put(route('almacen.update', $inventory), [
                'name' => 'Producto Editado',
                'quantity' => 1,
                'min_stock' => 2,
                'price' => 20,
                'description' => 'Editado',
                'active' => false,
            ])->assertRedirect(route('almacen.index'));

        $inventory->refresh();
        $this->assertEquals('Producto Editado', $inventory->name);
    }

    public function test_alerta_stock_minimo_se_muestra()
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        Inventory::factory()->create(['quantity' => 1, 'min_stock' => 2]);

        $this->actingAs($admin)
            ->get(route('almacen.index'))
            ->assertSee('Alerta de Stock Crítico');
    }

    public function test_barbero_solo_puede_ver()
    {
        $barbero = User::factory()->create();
        $barbero->assignRole('barbero');
        $inv = Inventory::factory()->create();

        $this->actingAs($barbero)
            ->get(route('almacen.index'))
            ->assertStatus(200);

        $this->actingAs($barbero)
            ->get(route('almacen.show', $inv))
            ->assertStatus(200);

        $this->actingAs($barbero)
            ->get(route('almacen.create'))
            ->assertForbidden();
    }

    public function test_cliente_no_puede_acceder()
    {
        $cliente = User::factory()->create();
        $cliente->assignRole('cliente');

        $this->actingAs($cliente)
            ->get(route('almacen.index'))
            ->assertForbidden();
    }
}
