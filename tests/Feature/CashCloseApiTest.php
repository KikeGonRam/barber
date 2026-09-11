<?php

namespace Tests\Feature;

use App\Models\CashClose;
use App\Models\MobileApiToken;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas.
 * Corte de caja (admin/recepción): GET/POST /api/v1/cash-closes.
 *
 * El punto delicado que fija este archivo es QUÉ cuenta como dinero del día.
 * Son dos fuentes, no una: los cobros de citas viven en Payment, pero las
 * ventas de tienda NO generan Payment -- Order lleva su propio total y
 * metodo_pago. Un corte que solo mirara pagos subreportaría todo el producto
 * vendido. Y solo cuenta el dinero realmente recibido: nunca una
 * transferencia rechazada ni una pendiente de revisar.
 */
class CashCloseApiTest extends TestCase
{
    private string $adminToken = 'test-cash-close-admin';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Caja', 'email' => 'admin-caja@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);
    }

    protected function tearDown(): void
    {
        CashClose::query()->delete();
        Payment::query()->delete();
        Order::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function payment(string $metodo, float $monto, string $estado = Payment::ESTADO_VERIFICADO, float $propina = 0): Payment
    {
        return Payment::create([
            'appointment_id' => (string) Str::uuid(),
            'monto' => $monto,
            'propina' => $propina,
            'metodo_pago' => $metodo,
            'estado' => $estado,
        ]);
    }

    private function deliveredOrder(string $metodo, float $total): Order
    {
        return Order::create([
            'client_id' => (string) Str::uuid(),
            'folio' => 'F-'.Str::random(6),
            'items' => [],
            'total' => $total,
            'estado' => 'entregado',
            'tipo' => 'tienda',
            'metodo_pago' => $metodo,
            'entregado_en' => now(),
        ]);
    }

    public function test_preview_sums_verified_payments_and_delivered_orders_by_method(): void
    {
        $this->payment('efectivo', 200, Payment::ESTADO_VERIFICADO, 50);
        $this->payment('tarjeta', 300);
        $this->deliveredOrder('efectivo', 150);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/cash-closes/preview');

        $response->assertOk();
        // efectivo: 200 + 50 de propina + 150 del pedido
        $this->assertEquals(400.0, $response->json('data.esperado.efectivo'));
        $this->assertEquals(300.0, $response->json('data.esperado.tarjeta'));
        $this->assertEquals(700.0, $response->json('data.esperado_total'));
        $this->assertEquals(400.0, $response->json('data.efectivo_esperado'));
        $this->assertEquals(50.0, $response->json('data.propinas'));
        $response->assertJsonPath('data.pagos', 2);
        $response->assertJsonPath('data.pedidos', 1);
    }

    public function test_preview_ignores_rejected_and_unverified_payments(): void
    {
        $this->payment('efectivo', 500, Payment::ESTADO_VERIFICADO);
        $this->payment('transferencia', 999, Payment::ESTADO_RECHAZADO);
        $this->payment('transferencia', 777, Payment::ESTADO_PENDIENTE_VERIFICACION);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/cash-closes/preview');

        $response->assertOk();
        $this->assertEquals(500.0, $response->json('data.esperado_total'));
        $this->assertNull($response->json('data.esperado.transferencia'));
    }

    public function test_preview_ignores_orders_that_were_not_delivered(): void
    {
        Order::create([
            'client_id' => (string) Str::uuid(),
            'folio' => 'F-PEND',
            'items' => [],
            'total' => 400,
            'estado' => 'pendiente',
            'tipo' => 'tienda',
            'metodo_pago' => 'efectivo',
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/cash-closes/preview');

        $response->assertOk();
        $this->assertEquals(0.0, $response->json('data.esperado_total'));
    }

    public function test_store_computes_the_difference_on_the_server(): void
    {
        $this->payment('efectivo', 1000);

        $response = $this->withToken($this->adminToken)->postJson('/api/v1/cash-closes', [
            'efectivo_contado' => 940,
            'notas' => 'Faltante por cambio.',
            // Aunque el cliente mandara una diferencia inventada, el servidor
            // la recalcula: es el dato que justifica todo el arqueo.
            'diferencia' => 999999,
        ]);

        $response->assertCreated();
        $this->assertEquals(1000.0, $response->json('data.efectivo_esperado'));
        $this->assertEquals(940.0, $response->json('data.efectivo_contado'));
        $this->assertEquals(-60.0, $response->json('data.diferencia'));
        $this->assertSame('Admin Caja', $response->json('data.cerrado_por_nombre'));
    }

    public function test_a_day_can_only_be_closed_once(): void
    {
        $this->payment('efectivo', 100);

        $this->withToken($this->adminToken)
            ->postJson('/api/v1/cash-closes', ['efectivo_contado' => 100])
            ->assertCreated();

        $this->withToken($this->adminToken)
            ->postJson('/api/v1/cash-closes', ['efectivo_contado' => 100])
            ->assertStatus(422);

        $this->assertSame(1, CashClose::count());
    }

    public function test_preview_reports_an_existing_close_for_that_day(): void
    {
        $this->payment('efectivo', 100);
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/cash-closes', ['efectivo_contado' => 100])
            ->assertCreated();

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/cash-closes/preview');

        $response->assertOk();
        $this->assertNotNull($response->json('data.cierre'));
    }

    public function test_counted_cash_is_required_and_cannot_be_negative(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/cash-closes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['efectivo_contado']);

        $this->withToken($this->adminToken)
            ->postJson('/api/v1/cash-closes', ['efectivo_contado' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['efectivo_contado']);
    }

    public function test_recepcion_can_close_the_register_and_a_barber_cannot(): void
    {
        $recepcion = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Recepción Caja', 'email' => 'recepcion-caja@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $recepcion->id]])->save();
        $recepcionToken = 'test-cash-recepcion';
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $recepcionToken)]);

        $this->withToken($recepcionToken)->getJson('/api/v1/cash-closes/preview')->assertOk();

        $barberoRole = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();
        $barbero = User::create(['name' => 'Barbero Caja', 'email' => 'barbero-caja@test.local', 'password' => 'password']);
        $barbero->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $barberoRole->id]])->save();
        $barberoToken = 'test-cash-barbero';
        MobileApiToken::create(['user_id' => (string) $barbero->id, 'name' => 'test', 'token_hash' => hash('sha256', $barberoToken)]);

        $this->withToken($barberoToken)->getJson('/api/v1/cash-closes/preview')->assertForbidden();
        $this->withToken($barberoToken)->postJson('/api/v1/cash-closes', ['efectivo_contado' => 10])->assertForbidden();
    }

    public function test_yesterday_money_does_not_leak_into_todays_close(): void
    {
        $viejo = $this->payment('efectivo', 900);
        $viejo->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->payment('efectivo', 100);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/cash-closes/preview');

        $response->assertOk();
        $this->assertEquals(100.0, $response->json('data.esperado_total'));
    }
}
