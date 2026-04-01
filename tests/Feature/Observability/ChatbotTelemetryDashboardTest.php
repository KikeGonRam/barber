<?php

namespace Tests\Feature\Observability;

use App\Models\BarbershopSetting;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatbotTelemetryDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
            'politica_cancelacion' => 24,
        ]);
    }

    public function test_admin_metrics_include_chatbot_telemetry_aggregates(): void
    {
        activity('chatbot')->withProperties([
            'source' => 'gemini',
            'status' => 'success',
            'latency_ms' => 280,
            'estimated_cost_usd' => 0.014,
        ])->log('chatbot_provider_telemetry');

        activity('chatbot')->withProperties([
            'source' => 'manual',
            'status' => 'fallback',
            'latency_ms' => 120,
            'estimated_cost_usd' => 0,
        ])->log('chatbot_provider_telemetry');

        activity('chatbot')->withProperties([
            'source' => 'intelligence',
            'status' => 'error',
            'latency_ms' => 50,
            'estimated_cost_usd' => 0,
        ])->log('chatbot_provider_telemetry');

        $metrics = app(DashboardService::class)->adminMetrics();

        $this->assertArrayHasKey('chatbot_telemetry', $metrics);

        $telemetry = $metrics['chatbot_telemetry'];

        $this->assertSame(3, $telemetry['total_requests']);
        $this->assertSame(1, $telemetry['errors']);
        $this->assertSame(33.33, $telemetry['error_rate_pct']);
        $this->assertSame(150, $telemetry['avg_latency_ms']);
        $this->assertSame(0.014, $telemetry['estimated_cost_usd']);
        $this->assertArrayHasKey('gemini', $telemetry['top_sources']);
    }

    public function test_admin_dashboard_renders_chatbot_telemetry_panel(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        activity('chatbot')->withProperties([
            'source' => 'rate_limit',
            'status' => 'blocked',
            'latency_ms' => 20,
            'estimated_cost_usd' => 0,
        ])->log('chatbot_provider_telemetry');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Telemetria Chatbot')
            ->assertSee('Error Rate')
            ->assertSee('rate limit');
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
