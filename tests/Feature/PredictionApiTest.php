<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas.
 * App\Http\Controllers\Api\Prediction\PredictionController
 * (admin/predictions/*) -- hasta ahora solo predictions/insights (la única
 * ruta sin llamada a Ollama) tenía cobertura, vía EngineerRoleAuthorizationTest.
 * income/appointments/services/peak-hours llaman a Ollama por Http::post():
 * se usa Http::fake() para no depender de la red/contenedor de Ollama.
 */
class PredictionApiTest extends TestCase
{
    private string $adminToken = 'test-prediction-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        // PredictionService::loadHistoricalData() cachea 6h bajo esta key fija
        // -- sin esto, un run previo (u otro test) deja datos históricos
        // stale y los fixtures de este test nunca se reflejan en la respuesta.
        Cache::forget('prediction_historical_data');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Predicciones', 'email' => 'admin-prediction@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);
    }

    protected function tearDown(): void
    {
        Cache::forget('prediction_historical_data');
        Appointment::withTrashed()->forceDelete();
        Payment::query()->delete();
        Service::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function completedAppointment(Service $service, ?string $horaInicio = '10:00:00'): Appointment
    {
        return Appointment::create([
            'client_id' => (string) Str::uuid(),
            'barber_id' => (string) Str::uuid(),
            'service_id' => (string) $service->id,
            'fecha' => now()->subDays(2)->format('Y-m-d'),
            'hora_inicio' => $horaInicio,
            'hora_fin' => '10:30:00',
            'estado' => 'completada',
            'precio_cobrado' => $service->precio,
        ]);
    }

    public function test_income_forecasting_returns_the_expected_shape(): void
    {
        Http::fake([
            'http://ollama:11434/*' => Http::response(['response' => 'Se estiman $5000 pesos, tendencia alcista.'], 200),
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/predictions/income/7');

        $response->assertOk();
        $response->assertJsonPath('type', 'income_forecast');
        $response->assertJsonPath('data.period', 'Próximos 7 días');
        $response->assertJsonPath('data.trend', 'up');
        $response->assertJsonStructure(['data' => ['predicted_income', 'confidence', 'reasoning']]);
    }

    public function test_appointment_forecast_returns_the_expected_shape(): void
    {
        Http::fake([
            'http://ollama:11434/*' => Http::response(['response' => 'Se esperan 12 citas, hora pico 10:00.'], 200),
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/predictions/appointments/7');

        $response->assertOk();
        $response->assertJsonPath('type', 'appointment_forecast');
        $response->assertJsonStructure(['data' => ['period', 'predicted_appointments', 'confidence', 'peak_hours', 'reasoning']]);
    }

    public function test_service_analysis_reflects_real_completed_appointments(): void
    {
        Http::fake([
            'http://ollama:11434/*' => Http::response(['response' => 'Corte tiene potencial de crecimiento.'], 200),
        ]);
        $service = Service::create(['nombre' => 'Corte Popular', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $this->completedAppointment($service);
        $this->completedAppointment($service);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/predictions/services');

        $response->assertOk();
        $response->assertJsonPath('type', 'service_analysis');
        $response->assertJsonPath('data.top_services.0.service', 'Corte Popular');
        $response->assertJsonPath('data.top_services.0.count', 2);
    }

    public function test_peak_hours_analysis_returns_the_expected_shape(): void
    {
        Http::fake([
            'http://ollama:11434/*' => Http::response(['response' => 'La hora pico es 10:00.'], 200),
        ]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $this->completedAppointment($service);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/predictions/peak-hours');

        $response->assertOk();
        $response->assertJsonPath('type', 'peak_hours');
        $response->assertJsonStructure(['data' => ['peak_hours', 'quiet_hours', 'recommendations', 'reasoning']]);
    }

    public function test_prediction_keeps_working_when_ollama_is_unreachable(): void
    {
        Http::fake([
            'http://ollama:11434/*' => Http::response('', 500),
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/predictions/income/7');

        $response->assertOk();
        $response->assertJsonPath('data.reasoning', 'Predicción no disponible en este momento.');
        $response->assertJsonPath('data.confidence', 0);
    }

    public function test_non_admin_cannot_reach_prediction_endpoints(): void
    {
        $role = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();
        $barbero = User::create(['name' => 'Barbero Test', 'email' => 'barbero-prediction@test.local', 'password' => 'password']);
        $barbero->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $token = 'test-non-admin-prediction-token';
        MobileApiToken::create(['user_id' => (string) $barbero->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/v1/admin/predictions/income/7')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/predictions/services')->assertForbidden();
    }
}
