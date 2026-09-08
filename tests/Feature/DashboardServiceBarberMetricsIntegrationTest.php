<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre barberMetrics()
 * de DashboardService, en particular el KPI 'rating': antes estaba
 * hardcodeado a 4.9 en vez de leer calificacion_promedio (denormalizado por
 * BarberReviewService::syncBarberRatingStats sobre el documento del barbero).
 */
class DashboardServiceBarberMetricsIntegrationTest extends TestCase
{
    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DashboardService::class);
    }

    protected function tearDown(): void
    {
        Barber::query()->delete();
        User::withTrashed()->forceDelete();

        parent::tearDown();
    }

    public function test_rating_kpi_reflects_the_barbers_real_average_rating(): void
    {
        $user = User::create(['name' => 'Barbero Reseñado', 'email' => 'resenado@test.local', 'password' => 'password']);
        $barber = Barber::create([
            'user_id' => (string) $user->id,
            'nombre' => 'Reseñado',
            'activo' => true,
        ]);
        // calificacion_promedio/total_resenas no son mass-assignable (ver
        // BarberReviewService::syncBarberRatingStats, que también los asigna
        // como propiedad directa en vez de mass assignment).
        $barber->setAttribute('calificacion_promedio', 3.7);
        $barber->setAttribute('total_resenas', 10);
        $barber->save();

        Cache::forget('dashboard.barber.'.$barber->id);
        $data = $this->service->barberMetrics((string) $barber->id);

        $this->assertSame(3.7, $data['kpis']['rating']);
    }

    public function test_rating_kpi_is_zero_for_a_barber_with_no_reviews_yet(): void
    {
        $user = User::create(['name' => 'Barbero Nuevo', 'email' => 'nuevo-sin-resenas@test.local', 'password' => 'password']);
        $barber = Barber::create([
            'user_id' => (string) $user->id,
            'nombre' => 'Nuevo',
            'activo' => true,
        ]);

        Cache::forget('dashboard.barber.'.$barber->id);
        $data = $this->service->barberMetrics((string) $barber->id);

        $this->assertSame(0.0, $data['kpis']['rating']);
    }
}
