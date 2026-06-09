<?php

namespace Tests\Unit\WhiteBox;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Services\DashboardService;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshMongoDatabase;

    private DashboardService $dashboardService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboardService = app(DashboardService::class);
    }

    public function test_admin_metrics_returns_expected_keys(): void
    {
        $metrics = $this->dashboardService->adminMetrics();

        $this->assertArrayHasKey('kpis', $metrics);
        $this->assertArrayHasKey('income_chart', $metrics);
        $this->assertArrayHasKey('services_chart', $metrics);
        $this->assertArrayHasKey('chatbot_telemetry', $metrics);

        $kpis = $metrics['kpis'];
        $this->assertArrayHasKey('appointments_today', $kpis);
        $this->assertArrayHasKey('appointments_week', $kpis);
        $this->assertArrayHasKey('appointments_month', $kpis);
        $this->assertArrayHasKey('income_today', $kpis);
        $this->assertArrayHasKey('income_week', $kpis);
        $this->assertArrayHasKey('income_month', $kpis);
        $this->assertArrayHasKey('new_clients', $kpis);
        $this->assertArrayHasKey('recurring_clients', $kpis);
        $this->assertArrayHasKey('low_stock_count', $kpis);
        $this->assertArrayHasKey('barbers_status', $kpis);
    }

    public function test_admin_metrics_counts_todays_appointments(): void
    {
        $barber = Barber::factory()->create();
        $client = Client::factory()->create();
        $service = Service::factory()->create();

        Appointment::factory()->create([
            'barber_id' => $barber->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'confirmada',
        ]);

        $metrics = $this->dashboardService->adminMetrics();

        $this->assertSame(1, $metrics['kpis']['appointments_today']);
    }

    public function test_admin_metrics_low_stock_count_is_accurate(): void
    {
        Product::factory()->create(['stock_actual' => 1, 'stock_minimo' => 5]);
        Product::factory()->create(['stock_actual' => 10, 'stock_minimo' => 5]);

        $metrics = $this->dashboardService->adminMetrics();

        $this->assertSame(1, $metrics['kpis']['low_stock_count']);
    }

    public function test_admin_metrics_income_chart_has_eight_entries(): void
    {
        $metrics = $this->dashboardService->adminMetrics();

        $this->assertCount(8, $metrics['income_chart']['labels']);
        $this->assertCount(8, $metrics['income_chart']['values']);
    }

    public function test_barber_metrics_returns_expected_keys(): void
    {
        $barber = Barber::factory()->create();

        $metrics = $this->dashboardService->barberMetrics($barber->id);

        $this->assertArrayHasKey('kpis', $metrics);
        $this->assertArrayHasKey('performance_chart', $metrics);
        $this->assertArrayHasKey('services_chart', $metrics);

        $kpis = $metrics['kpis'];
        $this->assertArrayHasKey('appointments_today', $kpis);
        $this->assertArrayHasKey('appointments_month', $kpis);
        $this->assertArrayHasKey('income_month', $kpis);
        $this->assertArrayHasKey('rating', $kpis);
    }

    public function test_barber_metrics_counts_only_barbers_appointments(): void
    {
        $barber1 = Barber::factory()->create();
        $barber2 = Barber::factory()->create();
        $client = Client::factory()->create();
        $service = Service::factory()->create();

        Appointment::factory()->create([
            'barber_id' => $barber1->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'confirmada',
        ]);

        $metrics = $this->dashboardService->barberMetrics($barber1->id);
        $this->assertSame(1, $metrics['kpis']['appointments_today']);

        $metricsOther = $this->dashboardService->barberMetrics($barber2->id);
        $this->assertSame(0, $metricsOther['kpis']['appointments_today']);
    }

    public function test_barber_metrics_performance_chart_has_seven_entries(): void
    {
        $barber = Barber::factory()->create();

        $metrics = $this->dashboardService->barberMetrics($barber->id);

        $this->assertCount(7, $metrics['performance_chart']['labels']);
        $this->assertCount(7, $metrics['performance_chart']['values']);
    }

    public function test_receptionist_metrics_returns_expected_keys(): void
    {
        $metrics = $this->dashboardService->receptionistMetrics();

        $this->assertArrayHasKey('kpis', $metrics);
        $this->assertArrayHasKey('next_appointments', $metrics);
        $this->assertArrayHasKey('flow_chart', $metrics);

        $kpis = $metrics['kpis'];
        $this->assertArrayHasKey('appointments_today', $kpis);
        $this->assertArrayHasKey('pending_payments', $kpis);
        $this->assertArrayHasKey('new_clients_today', $kpis);
        $this->assertArrayHasKey('low_stock_count', $kpis);
    }

    public function test_client_metrics_returns_expected_keys(): void
    {
        $client = Client::factory()->create();

        $metrics = $this->dashboardService->clientMetrics($client->id);

        $this->assertArrayHasKey('kpis', $metrics);
        $kpis = $metrics['kpis'];
        $this->assertArrayHasKey('total_appointments', $kpis);
        $this->assertArrayHasKey('completed_appointments', $kpis);
        $this->assertArrayHasKey('favorite_barber', $kpis);
        $this->assertArrayHasKey('membership_status', $kpis);
        $this->assertArrayHasKey('next_appointment', $metrics);
        $this->assertArrayHasKey('visit_chart', $metrics);
    }

    public function test_client_metrics_membership_status_caballero_by_default(): void
    {
        $client = Client::factory()->create();

        $metrics = $this->dashboardService->clientMetrics($client->id);

        $this->assertSame('Caballero', $metrics['kpis']['membership_status']);
    }

    public function test_client_metrics_membership_status_vip_at_five_completed(): void
    {
        $barber = Barber::factory()->create();
        $service = Service::factory()->create();
        $client = Client::factory()->create();

        Appointment::factory()->count(5)->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'estado' => 'completada',
        ]);

        $metrics = $this->dashboardService->clientMetrics($client->id);

        $this->assertSame('V.I.P', $metrics['kpis']['membership_status']);
    }

    public function test_client_metrics_membership_status_leyenda_at_ten_completed(): void
    {
        $barber = Barber::factory()->create();
        $service = Service::factory()->create();
        $client = Client::factory()->create();

        Appointment::factory()->count(10)->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'estado' => 'completada',
        ]);

        $metrics = $this->dashboardService->clientMetrics($client->id);

        $this->assertSame('Leyenda', $metrics['kpis']['membership_status']);
    }

    public function test_client_metrics_visit_chart_has_six_entries(): void
    {
        $client = Client::factory()->create();

        $metrics = $this->dashboardService->clientMetrics($client->id);

        $this->assertCount(6, $metrics['visit_chart']['labels']);
        $this->assertCount(6, $metrics['visit_chart']['values']);
    }

    public function test_client_metrics_counts_total_and_completed_appointments(): void
    {
        $barber = Barber::factory()->create();
        $service = Service::factory()->create();
        $client = Client::factory()->create();

        Appointment::factory()->count(3)->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'estado' => 'completada',
        ]);

        Appointment::factory()->count(2)->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'estado' => 'pendiente',
        ]);

        $metrics = $this->dashboardService->clientMetrics($client->id);

        $this->assertSame(5, $metrics['kpis']['total_appointments']);
        $this->assertSame(3, $metrics['kpis']['completed_appointments']);
    }
}
