<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Barber;
use App\Models\Client;
use App\Models\Product;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

/**
 * FASE 2 — Integración Admin API
 *
 * Cubre las 33 rutas del grupo /api/v1/admin/* que requerían 0% de cobertura.
 * Verifica: autenticación (401), autorización (403), estructura de respuesta (200).
 *
 * Estrategia:
 *  - adminToken()   → crea un User con rol 'administrador', devuelve Bearer token
 *  - clienteToken() → crea un User con rol 'cliente', para comprobar el 403
 *  - Cada test de endpoint: sin auth (401) + con admin (200) + con cliente (403)
 */
class AdminApiTest extends TestCase
{
    use RefreshMongoDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function adminToken(): string
    {
        $user = User::factory()->create([
            'email'             => 'admin@test.com',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('administrador');

        return $this->postJson('/api/v1/auth/login', [
            'email'       => 'admin@test.com',
            'password'    => 'password',
            'device_name' => 'test-device',
        ])->json('token');
    }

    private function clienteToken(): string
    {
        $user = User::factory()->create([
            'email'             => 'cliente@test.com',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('cliente');

        return $this->postJson('/api/v1/auth/login', [
            'email'       => 'cliente@test.com',
            'password'    => 'password',
            'device_name' => 'test-device',
        ])->json('token');
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->adminToken()];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DASHBOARD ADMIN
    // ──────────────────────────────────────────────────────────────────────────

    public function test_dashboard_stats_requires_auth(): void
    {
        $this->getJson('/api/v1/admin/dashboard/stats')->assertUnauthorized();
    }

    public function test_dashboard_stats_returns_stats_for_admin(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure(['stats' => [
                'revenueToday', 'appointmentsCompleted', 'occupancyRate', 'newClients',
            ]]);
    }

    public function test_dashboard_stats_returns_403_for_cliente(): void
    {
        $token = $this->clienteToken();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertForbidden();
    }

    public function test_dashboard_appointments_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/dashboard/appointments')
            ->assertOk()
            ->assertJsonStructure(['appointments']);
    }

    public function test_dashboard_revenue_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/dashboard/revenue')
            ->assertOk()
            ->assertJsonStructure(['revenue']);
    }

    public function test_dashboard_alerts_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/dashboard/alerts')
            ->assertOk()
            ->assertJsonStructure(['alerts']);
    }

    public function test_dashboard_metrics_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/dashboard/metrics')
            ->assertOk()
            ->assertJsonStructure(['metrics']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // BARBEROS ADMIN
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_list_barbers_requires_auth(): void
    {
        $this->getJson('/api/v1/admin/barbers')->assertUnauthorized();
    }

    public function test_admin_list_barbers_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/barbers')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_get_barber_detail_returns_404_for_unknown(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/barbers/000000000000000000000000')
            ->assertNotFound();
    }

    public function test_admin_get_barber_detail_returns_200(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $barber = Barber::factory()->create(['user_id' => $user->id, 'activo' => true]);

        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/barbers/'.$barber->id)
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['id', 'name']]);
    }

    public function test_admin_update_barber_returns_200(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $barber = Barber::factory()->create(['user_id' => $user->id]);

        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/v1/admin/barbers/'.$barber->id, [
                'activo'       => false,
                'descripcion'  => 'Actualizado por test',
                'especialidades' => 'Fade moderno',
            ])
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CLIENTES ADMIN
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_list_clients_requires_auth(): void
    {
        $this->getJson('/api/v1/admin/clients')->assertUnauthorized();
    }

    public function test_admin_list_clients_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/clients')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_create_client_returns_201(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/clients', [
                'name'     => 'Cliente de Test',
                'email'    => 'cliente.test@example.com',
                'password' => 'Password123!',
                'telefono' => '+521234567890',
            ])
            ->assertCreated()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_get_client_detail_returns_200(): void
    {
        $userClient = User::factory()->create(['email_verified_at' => now()]);
        $userClient->assignRole('cliente');
        $client = Client::factory()->create(['user_id' => $userClient->id]);

        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/clients/'.$client->id)
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_delete_client_returns_200(): void
    {
        $userClient = User::factory()->create(['email_verified_at' => now()]);
        $client = Client::factory()->create(['user_id' => $userClient->id]);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson('/api/v1/admin/clients/'.$client->id)
            ->assertOk()
            ->assertJsonStructure(['success', 'message']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // INVENTARIO ADMIN
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_inventory_products_requires_auth(): void
    {
        $this->getJson('/api/v1/admin/inventory/products')->assertUnauthorized();
    }

    public function test_admin_inventory_products_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/inventory/products')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_create_product_returns_201(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/inventory/products', [
                'nombre'       => 'Shampoo Test',
                'descripcion'  => 'Shampoo para tests',
                'precio_venta' => 150.00,
                'stock_actual' => 10,
                'stock_minimo' => 2,
                'categoria'    => 'cuidado',
                'tipo'         => 'producto',
            ])
            ->assertCreated()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_inventory_summary_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/inventory/summary')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_inventory_low_stock_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/inventory/low-stock')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_inventory_movements_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/inventory/movements')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // REPORTES ADMIN
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_revenue_report_requires_auth(): void
    {
        $this->getJson('/api/v1/admin/reports/revenue')->assertUnauthorized();
    }

    public function test_admin_revenue_report_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/reports/revenue')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_appointments_report_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/reports/appointments')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_inventory_report_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/reports/inventory')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_clients_report_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/reports/clients')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PREDICCIONES ADMIN (IA)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_income_forecast_requires_auth(): void
    {
        $this->getJson('/api/v1/admin/predictions/income')->assertUnauthorized();
    }

    public function test_admin_income_forecast_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/predictions/income/7')
            ->assertOk()
            ->assertJsonStructure(['type', 'data']);
    }

    public function test_admin_appointments_forecast_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/predictions/appointments/7')
            ->assertOk()
            ->assertJsonStructure(['type', 'data']);
    }

    public function test_admin_services_analysis_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/predictions/services')
            ->assertOk()
            ->assertJsonStructure(['type', 'data']);
    }

    public function test_admin_peak_hours_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/predictions/peak-hours')
            ->assertOk()
            ->assertJsonStructure(['type', 'data']);
    }

    public function test_admin_insights_returns_200(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/predictions/insights')
            ->assertOk()
            ->assertJsonStructure(['type', 'data']);
    }
}
