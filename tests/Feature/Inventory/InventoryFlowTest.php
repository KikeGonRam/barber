<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_product(): void
    {
        $admin = $this->makeUserWithRole('administrador');

        $this->actingAs($admin)
            ->post(route('inventory.products.store'), [
                'nombre' => 'Pomada Mate',
                'categoria' => 'Estilizado',
                'descripcion' => 'Acabado natural',
                'precio_compra' => 80,
                'precio_venta' => 150,
                'stock_actual' => 10,
                'stock_minimo' => 3,
                'tipo' => 'venta_cliente',
            ])
            ->assertRedirect(route('inventory.products.index'));

        $this->assertDatabaseHas('products', [
            'nombre' => 'Pomada Mate',
            'stock_actual' => 10,
        ]);
    }

    public function test_recepcionista_can_register_salida_and_decrease_stock(): void
    {
        $recepcionista = $this->makeUserWithRole('recepcionista');

        $product = Product::query()->create([
            'nombre' => 'Toalla Desechable',
            'categoria' => 'Insumos',
            'precio_compra' => 5,
            'precio_venta' => 10,
            'stock_actual' => 20,
            'stock_minimo' => 5,
            'tipo' => 'insumo_trabajo',
        ]);

        $this->actingAs($recepcionista)
            ->post(route('inventory.movements.store'), [
                'product_id' => $product->id,
                'tipo' => 'salida',
                'cantidad' => 4,
                'motivo' => 'Uso en servicio',
            ])
            ->assertRedirect(route('inventory.movements.index'));

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'tipo' => 'salida',
            'cantidad' => 4,
        ]);

        $this->assertSame(16, (int) $product->fresh()->stock_actual);
    }

    public function test_recepcionista_cannot_register_entrada(): void
    {
        $recepcionista = $this->makeUserWithRole('recepcionista');

        $product = Product::query()->create([
            'nombre' => 'Shampoo',
            'categoria' => 'Insumos',
            'precio_compra' => 25,
            'precio_venta' => 40,
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'tipo' => 'insumo_trabajo',
        ]);

        $this->from(route('inventory.movements.create'))
            ->actingAs($recepcionista)
            ->post(route('inventory.movements.store'), [
                'product_id' => $product->id,
                'tipo' => 'entrada',
                'cantidad' => 5,
            ])
            ->assertRedirect(route('inventory.movements.create'))
            ->assertSessionHasErrors('tipo');

        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_cannot_register_salida_with_insufficient_stock(): void
    {
        $admin = $this->makeUserWithRole('administrador');

        $product = Product::query()->create([
            'nombre' => 'Navajas',
            'categoria' => 'Insumos',
            'precio_compra' => 15,
            'precio_venta' => 30,
            'stock_actual' => 2,
            'stock_minimo' => 1,
            'tipo' => 'insumo_trabajo',
        ]);

        $this->from(route('inventory.movements.create'))
            ->actingAs($admin)
            ->post(route('inventory.movements.store'), [
                'product_id' => $product->id,
                'tipo' => 'salida',
                'cantidad' => 3,
            ])
            ->assertRedirect(route('inventory.movements.create'))
            ->assertSessionHasErrors('cantidad');

        $this->assertSame(2, (int) $product->fresh()->stock_actual);
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
