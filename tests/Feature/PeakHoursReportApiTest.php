<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reporte de horas pico (GET /api/v1/admin/reports/peak-hours): demanda por día de la semana y hora
 * calculada con las citas reales, sin pasar por el modelo de lenguaje.
 */
class PeakHoursReportApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ([Appointment::class, MobileApiToken::class, User::class, Role::class, Permission::class] as $model) {
            $model::query()->delete();
        }
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    /** @return string token Bearer de un usuario con el rol indicado */
    private function tokenFor(string $role): string
    {
        $roleModel = Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($role), 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $roleModel->id]])->save();
        $token = 'tok-'.Str::uuid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        return $token;
    }

    private function cita(Carbon $fecha, string $hora, string $estado = 'completada'): void
    {
        Appointment::create([
            'client_id' => (string) Str::uuid(),
            'barber_id' => (string) Str::uuid(),
            'service_id' => (string) Str::uuid(),
            'fecha' => $fecha->format('Y-m-d'),
            'hora_inicio' => $hora,
            'hora_fin' => $hora,
            'estado' => $estado,
        ]);
    }

    public function test_calcula_la_demanda_por_dia_y_hora_con_las_citas_reales(): void
    {
        // El lunes se fija explícito: con el idioma es_MX Carbon empieza la semana en domingo.
        $lunes = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeek();
        $martes = $lunes->copy()->addDay();

        $this->cita($lunes, '10:00:00', 'completada');
        $this->cita($lunes, '10:30:00', 'pendiente');
        $this->cita($lunes, '10:45:00', 'confirmada');
        $this->cita($lunes, '11:00:00', 'completada');
        $this->cita($martes, '10:00:00', 'completada');
        $this->cita($martes, '10:15:00', 'no_asistio');
        // No cuentan: una cancelada y una cita fuera de la ventana de 90 días.
        $this->cita($lunes, '10:00:00', 'cancelada');
        $this->cita(Carbon::now()->subDays(200), '10:00:00', 'completada');

        $response = $this->withToken($this->tokenFor('administrador'))->getJson('/api/v1/admin/reports/peak-hours');

        $response->assertOk()->assertJsonPath('success', true);
        $data = $response->json('data');

        $this->assertSame(90, $data['days']);
        $this->assertSame(6, $data['totalAppointments']);
        $this->assertSame([10, 11], $data['hours']);
        $this->assertSame([3, 1], $data['matrix'][0], 'Lunes: 3 citas a las 10 y 1 a las 11');
        $this->assertSame([2, 0], $data['matrix'][1], 'Martes: 2 citas a las 10');
        $this->assertSame([0, 0], $data['matrix'][2], 'Miércoles sin actividad');

        $this->assertSame('Lunes 10:00', $data['peakSlots'][0]['label']);
        $this->assertSame(3, $data['peakSlots'][0]['total']);
        $this->assertSame('Martes 10:00', $data['peakSlots'][1]['label']);

        $this->assertSame(5, $data['hourlyTotals'][0]['total']);
        $this->assertSame('10:00', $data['hourlyTotals'][0]['label']);
        $this->assertSame(1, $data['hourlyTotals'][1]['total']);

        $this->assertSame('Martes 11:00', $data['quietSlots'][0]['label'], 'La hora más tranquila de un día con actividad');
        $this->assertSame(0, $data['quietSlots'][0]['total']);
        $this->assertArrayNotHasKey('dayTotal', $data['peakSlots'][0]);
    }

    public function test_sin_citas_devuelve_estructuras_vacias(): void
    {
        $response = $this->withToken($this->tokenFor('administrador'))->getJson('/api/v1/admin/reports/peak-hours');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame(0, $data['totalAppointments']);
        $this->assertSame([], $data['hours']);
        $this->assertSame([], $data['matrix']);
        $this->assertSame([], $data['peakSlots']);
    }

    public function test_una_ventana_no_permitida_usa_90_dias(): void
    {
        $token = $this->tokenFor('administrador');

        $this->withToken($token)->getJson('/api/v1/admin/reports/peak-hours?days=7')->assertOk()->assertJsonPath('data.days', 90);
        $this->withToken($token)->getJson('/api/v1/admin/reports/peak-hours?days=30')->assertOk()->assertJsonPath('data.days', 30);
    }

    public function test_el_cliente_no_puede_verlo_y_el_ingeniero_si(): void
    {
        $this->withToken($this->tokenFor('cliente'))->getJson('/api/v1/admin/reports/peak-hours')->assertForbidden();
        $this->withToken($this->tokenFor('ingeniero'))->getJson('/api/v1/admin/reports/peak-hours')->assertOk();
    }
}
