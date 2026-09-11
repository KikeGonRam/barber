<?php

namespace Tests\Feature;

use App\Models\BarbershopSetting;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas.
 * GET /api/v1/barbershop (CatalogController::barbershop): ficha pública del
 * negocio que consumen la landing y la reserva pública, ambas sin token.
 * El punto crítico de este archivo es que NO se filtren datos bancarios ni
 * banderas internas: la fuente es la misma fila que administra
 * SettingController, que sí los expone a un administrador.
 */
class PublicBarbershopApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(BarbershopSetting::CACHE_KEY);
    }

    protected function tearDown(): void
    {
        BarbershopSetting::query()->delete();
        Cache::forget(BarbershopSetting::CACHE_KEY);

        parent::tearDown();
    }

    private function setting(array $overrides = []): BarbershopSetting
    {
        return BarbershopSetting::create(array_merge([
            'nombre' => 'UrbanBlade Centro',
            'direccion' => 'Av. Juárez 120, Centro',
            'telefono' => '+52 55 1234 5678',
            'horario_apertura' => '09:00',
            'horario_cierre' => '21:00',
            'politica_cancelacion' => 24,
            'redes_sociales' => ['instagram' => 'urbanblade', 'facebook' => null, 'tiktok' => null],
            'datos_bancarios' => ['clabe' => '012345678901234567', 'banco' => 'BBVA', 'beneficiario' => 'UrbanBlade SA'],
            'maintenance_mode' => false,
        ], $overrides));
    }

    public function test_returns_the_public_profile_without_a_token(): void
    {
        $this->setting();

        $response = $this->getJson('/api/v1/barbershop');

        $response->assertOk();
        $response->assertJsonPath('data.nombre', 'UrbanBlade Centro');
        $response->assertJsonPath('data.direccion', 'Av. Juárez 120, Centro');
        $response->assertJsonPath('data.telefono', '+52 55 1234 5678');
        $response->assertJsonPath('data.horario_apertura', '09:00');
        $response->assertJsonPath('data.horario_cierre', '21:00');
        $response->assertJsonPath('data.politica_cancelacion', 24);
        $response->assertJsonPath('data.redes_sociales.instagram', 'urbanblade');
    }

    public function test_never_exposes_bank_details_or_internal_flags(): void
    {
        $this->setting();

        $response = $this->getJson('/api/v1/barbershop');

        $response->assertOk();
        // Ni la llave ni el valor: un visitante anónimo no debe poder leer la
        // CLABE de la barbería por una ficha pública.
        $response->assertJsonMissingPath('data.datos_bancarios');
        $response->assertJsonMissingPath('data.maintenance_mode');
        $this->assertStringNotContainsString('012345678901234567', $response->getContent());
        $this->assertStringNotContainsString('BBVA', $response->getContent());
    }

    public function test_responds_with_null_fields_when_the_barbershop_is_not_configured_yet(): void
    {
        // Instalación recién sembrada: no hay fila de configuración todavía.
        // La landing pública no debe reventar por eso.
        $response = $this->getJson('/api/v1/barbershop');

        $response->assertOk();
        $response->assertJsonPath('data.nombre', null);
        $response->assertJsonPath('data.direccion', null);
        $response->assertJsonPath('data.redes_sociales', []);
    }

    public function test_stays_reachable_for_a_guest_while_maintenance_mode_is_on(): void
    {
        // Es ruta pública (fuera de mobile.auth), así que maintenance.check no
        // corre: la ficha del negocio sigue visible igual que el catálogo,
        // mismo criterio que ApiMaintenanceModeTest documenta para /services.
        $this->setting(['maintenance_mode' => true]);

        $this->getJson('/api/v1/barbershop')->assertOk();
    }
}
