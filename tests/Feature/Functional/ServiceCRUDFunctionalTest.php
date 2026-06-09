<?php

namespace Tests\Feature\Functional;

/**
 * Cubre flujos funcionales de negocio para servicios, productos,
 * movimientos de inventario y exportacion de reportes.
 */

use App\Models\BarbershopSetting;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\Support\RefreshMongoDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceCRUDFunctionalTest extends TestCase
{
    use RefreshMongoDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
        ]);

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
        ]);
    }

    // --- Contexto: servicios ---

    public function test_admin_creates_service_and_it_appears_in_listing(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $this->actingAs($admin)
            ->post(route('services.store'), [
                'nombre' => 'Corte Ejecutivo Funcional',
                'categoria' => 'corte',
                'precio' => 300,
                'duracion_min' => 40,
                'descripcion' => 'Servicio funcional de prueba',
                'activo' => 1,
            ])
            ->assertRedirect(route('services.index'));

        $this->actingAs($admin)
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee('Corte Ejecutivo Funcional');
    }

    public function test_admin_edits_service_and_changes_persist(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $service = Service::factory()->create([
            'nombre' => 'Servicio Original',
            'activo' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('services.update', $service), [
                'nombre' => 'Servicio Editado',
                'categoria' => $service->categoria,
                'precio' => 420,
                'duracion_min' => 55,
                'descripcion' => 'Actualizado',
                'activo' => 1,
            ])
            ->assertRedirect(route('services.index'));

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'nombre' => 'Servicio Editado',
            'precio' => 420,
            'duracion_min' => 55,
        ]);
    }

    public function test_admin_deactivates_service_and_it_disappears_from_active_public_listing(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $service = Service::factory()->create([
            'nombre' => 'Servicio Desactivable',
            'activo' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('services.update', $service), [
                'nombre' => $service->nombre,
                'categoria' => $service->categoria,
                'precio' => $service->precio,
                'duracion_min' => $service->duracion_min,
                'descripcion' => $service->descripcion,
                'activo' => 0,
            ])
            ->assertRedirect(route('services.index'));

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'activo' => 0,
        ]);

        $this->get(route('services.public.index'))
            ->assertOk()
            ->assertDontSee('Servicio Desactivable');
    }

    public function test_admin_activates_inactive_service_and_it_reappears_in_active_public_listing(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $service = Service::factory()->create([
            'nombre' => 'Servicio Reactivable',
            'activo' => false,
        ]);

        $this->get(route('services.public.index'))
            ->assertOk()
            ->assertDontSee('Servicio Reactivable');

        $this->actingAs($admin)
            ->put(route('services.update', $service), [
                'nombre' => $service->nombre,
                'categoria' => $service->categoria,
                'precio' => $service->precio,
                'duracion_min' => $service->duracion_min,
                'descripcion' => $service->descripcion,
                'activo' => 1,
            ])
            ->assertRedirect(route('services.index'));

        $this->get(route('services.public.index'))
            ->assertOk()
            ->assertSee('Servicio Reactivable');
    }

    // --- Contexto: productos e inventario ---

    public function test_admin_creates_product_with_correct_initial_stock(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $this->actingAs($admin)
            ->post(route('inventory.products.store'), [
                'nombre' => 'Producto Funcional',
                'categoria' => 'insumos',
                'descripcion' => 'Prueba funcional',
                'precio_compra' => 100,
                'precio_venta' => 180,
                'stock_actual' => 25,
                'stock_minimo' => 5,
                'tipo' => 'insumo_trabajo',
            ])
            ->assertRedirect(route('inventory.products.index'));

        $this->assertDatabaseHas('products', [
            'nombre' => 'Producto Funcional',
            'stock_actual' => 25,
        ]);
    }

    public function test_recepcionista_registers_output_and_stock_decreases_correctly(): void
    {
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        $product = Product::factory()->create([
            'stock_actual' => 12,
            'stock_minimo' => 3,
            'tipo' => 'insumo_trabajo',
        ]);

        $this->actingAs($recepcionista)
            ->post(route('inventory.movements.store'), [
                'product_id' => $product->id,
                'tipo' => 'salida',
                'cantidad' => 2,
                'motivo' => 'Consumo operativo',
            ])
            ->assertRedirect(route('inventory.movements.index'));

        $this->assertSame(10, (int) $product->fresh()->stock_actual);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'tipo' => 'salida',
            'cantidad' => 2,
        ]);
    }

    public function test_recepcionista_cannot_register_input_movement(): void
    {
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        $product = Product::factory()->create([
            'stock_actual' => 12,
            'tipo' => 'insumo_trabajo',
        ]);

        $this->from(route('inventory.movements.create'))
            ->actingAs($recepcionista)
            ->post(route('inventory.movements.store'), [
                'product_id' => $product->id,
                'tipo' => 'entrada',
                'cantidad' => 3,
                'motivo' => 'Intento no permitido',
            ])
            ->assertRedirect(route('inventory.movements.create'))
            ->assertSessionHasErrors('tipo');

        $this->assertDatabaseMissing('inventory_movements', [
            'product_id' => $product->id,
            'tipo' => 'entrada',
        ]);
    }

    // --- Contexto: exportaciones ---

    public function test_admin_exports_income_report_pdf_with_pdf_content_type(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['type' => 'ingresos', 'format' => 'pdf']))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_admin_exports_income_report_excel_with_excel_content_type(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['type' => 'ingresos', 'format' => 'excel']))
            ->assertOk();

        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
    }

    private function createVerifiedUserWithRole(string $roleName): User
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
