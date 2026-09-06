<?php

namespace Tests\Feature;

use App\Models\AnalyticsInsight;
use App\Models\Barber;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * API del centro analítico (Fase Analítica), puerto de
 * Analytics\AnalyticsController (web): mismo AnalyticsInsightService, mismas
 * secciones/kpis/sparkFlow/visualCoverage, serializados a JSON. Antes no
 * existía ninguna API para esto — bloqueaba la fase Analítica del lado Nuxt.
 */
class AnalyticsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $this->forgetAnalyticsCache();
    }

    protected function tearDown(): void
    {
        AnalyticsInsight::query()->delete();
        Barber::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->forgetAnalyticsCache();

        parent::tearDown();
    }

    private function tokenFor(User $user): string
    {
        $plaintext = 'test-analytics-'.Str::uuid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plaintext)]);

        return $plaintext;
    }

    /**
     * Solo las claves de caché que este archivo puede haber escrito
     * (AnalyticsInsightService::porRol()/forBarber()) — nunca Cache::flush(),
     * que en tests corre sobre el store 'array' compartido por TODA la
     * suite dentro del mismo proceso PHP (php artisan test no es paralelo
     * aquí) y ya rompió otras clases de test (permisos de Spatie cacheados,
     * etc.) la primera vez que se probó con flush() global.
     */
    private function forgetAnalyticsCache(?string $barberUserId = null, ?string $barberProfileId = null): void
    {
        foreach (['administrador', 'recepcionista', 'cliente'] as $rol) {
            Cache::forget("analytics_insights.{$rol}");
        }

        if ($barberUserId !== null) {
            Cache::forget("analytics_insights.barbero.{$barberUserId}.{$barberProfileId}");
        }
    }

    public function test_admin_gets_sections_kpis_and_spark_flow(): void
    {
        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Analytics', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        AnalyticsInsight::create([
            'tipo' => 'resumen_ejecutivo', 'unidad' => 'II', 'roles' => ['administrador'],
            'titulo' => 'Resumen', 'mensaje' => 'Ingresos van bien.', 'valor_destacado' => '$12,000',
            'color' => 'gold', 'grafica' => ['tipo' => 'line', 'labels' => ['Ene', 'Feb'], 'valores' => [100, 200]],
            'generado_en' => now(),
        ]);
        AnalyticsInsight::create([
            'tipo' => 'clientes_en_riesgo', 'unidad' => 'III', 'roles' => ['administrador'],
            'titulo' => 'Clientes en riesgo', 'mensaje' => '5 clientes sin visitar en 30 días.', 'valor_destacado' => '5 clientes',
            'color' => 'danger', 'grafica' => null, 'generado_en' => now(),
        ]);

        $response = $this->withToken($this->tokenFor($admin))->getJson('/api/v1/analytics');

        $response->assertOk()
            ->assertJsonPath('rol_label', 'administrador')
            ->assertJsonCount(1, 'secciones.resumen.insights')
            ->assertJsonPath('secciones.resumen.insights.0.tipo', 'resumen_ejecutivo')
            ->assertJsonCount(1, 'secciones.clientes.insights')
            ->assertJsonPath('secciones.clientes.insights.0.tipo', 'clientes_en_riesgo')
            ->assertJsonStructure(['kpis', 'spark_flow', 'visual_coverage', 'diagnostico_insights']);

        $kpiLabels = collect($response->json('kpis'))->pluck('label');
        $this->assertTrue($kpiLabels->contains('Ingresos acumulados'));
        $this->assertTrue($kpiLabels->contains('Clientes en riesgo'));
    }

    public function test_barber_only_sees_own_private_insights(): void
    {
        $role = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();
        $barberUser = User::create(['name' => 'Barbero Uno', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barberUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Uno', 'activo' => true]);

        $otherBarberUser = User::create(['name' => 'Barbero Dos', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $otherBarber = Barber::create(['user_id' => (string) $otherBarberUser->id, 'nombre' => 'Barbero Dos', 'activo' => true]);

        AnalyticsInsight::create([
            'tipo' => 'utilizacion_propia', 'unidad' => 'III', 'roles' => ['barbero'],
            'barbero_perfil_id' => (string) $barber->id,
            'titulo' => 'Tu ocupación', 'mensaje' => '80% de tu agenda ocupada.', 'valor_destacado' => '80%',
            'generado_en' => now(),
        ]);
        AnalyticsInsight::create([
            'tipo' => 'utilizacion_propia', 'unidad' => 'III', 'roles' => ['barbero'],
            'barbero_perfil_id' => (string) $otherBarber->id,
            'titulo' => 'Tu ocupación', 'mensaje' => '40% de tu agenda ocupada.', 'valor_destacado' => '40%',
            'generado_en' => now(),
        ]);

        $this->forgetAnalyticsCache((string) $barberUser->id, (string) $barber->id);

        $response = $this->withToken($this->tokenFor($barberUser))->getJson('/api/v1/analytics');

        $response->assertOk()
            ->assertJsonPath('rol_label', 'barbero')
            ->assertJsonCount(1, 'secciones.operacion.insights')
            ->assertJsonPath('secciones.operacion.insights.0.valor_destacado', '80%');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/analytics')->assertStatus(401);
    }
}
