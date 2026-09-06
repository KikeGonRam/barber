<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Autoservicio de cliente vía API (Fase 9.8): stats/próxima cita en el
 * listado, reagendar y cancelar citas propias respetando la política de
 * cancelación (BarbershopSetting::politica_cancelacion), y el historial de
 * pagos/facturas propio (antes solo disponible para administración/recepción).
 */
class ClientSelfServiceApiTest extends TestCase
{
    private Client $client;

    private string $clientToken;

    private Barber $barber;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $clientUser = User::create(['name' => 'Cliente Self Service', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $clientUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $this->client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5550002222', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $barberUser = User::create(['name' => 'Barbero Self Service', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $this->barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Self Service', 'activo' => true]);
        $this->service = Service::create(['nombre' => 'Corte Self Service', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        $this->clientToken = 'test-client-self-service-token';
        MobileApiToken::create(['user_id' => (string) $clientUser->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->clientToken)]);
    }

    protected function tearDown(): void
    {
        Payment::query()->delete();
        Appointment::withTrashed()->forceDelete();
        Service::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function makeAppointment(array $overrides = []): Appointment
    {
        return Appointment::create(array_merge([
            'client_id' => (string) $this->client->id,
            'barber_id' => (string) $this->barber->id,
            'service_id' => (string) $this->service->id,
            'fecha' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ], $overrides));
    }

    public function test_client_appointments_index_includes_stats_next_and_policy(): void
    {
        $this->makeAppointment(['fecha' => now()->subDays(10)->toDateString(), 'estado' => 'completada']);
        $upcomingSoon = $this->makeAppointment(['fecha' => now()->addDays(2)->toDateString(), 'estado' => 'pendiente']);
        $this->makeAppointment(['fecha' => now()->addDays(5)->toDateString(), 'estado' => 'confirmada']);

        $response = $this->withToken($this->clientToken)->getJson('/api/v1/appointments');

        $response->assertOk()
            ->assertJsonPath('stats.total', 3)
            ->assertJsonPath('stats.proximas', 2)
            ->assertJsonPath('stats.completadas', 1)
            ->assertJsonPath('stats.canceladas', 0)
            ->assertJsonPath('next.code', (string) $upcomingSoon->getAttribute('code'))
            ->assertJsonPath('cancellation_policy_hours', 24);
    }

    public function test_client_can_reschedule_own_pending_appointment(): void
    {
        $appointment = $this->makeAppointment(['estado' => 'pendiente', 'fecha' => now()->addDays(5)->toDateString()]);
        $newBarberUser = User::create(['name' => 'Otro Barbero', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $newBarber = Barber::create(['user_id' => (string) $newBarberUser->id, 'nombre' => 'Otro Barbero', 'activo' => true]);

        $response = $this->withToken($this->clientToken)->putJson("/api/v1/appointments/{$appointment->getAttribute('code')}", [
            'barber_id' => (string) $newBarber->id,
            'service_id' => (string) $this->service->id,
            'fecha' => now()->addDays(7)->toDateString(),
            'hora_inicio' => '14:00',
            'notas' => 'Reagendada por el cliente',
        ]);

        $response->assertOk()->assertJsonPath('data.estado', 'pendiente');

        $appointment->refresh();
        $this->assertSame((string) $newBarber->id, (string) $appointment->barber_id);
        $this->assertSame(now()->addDays(7)->toDateString(), substr((string) $appointment->fecha, 0, 10));
    }

    public function test_client_cannot_reschedule_a_completed_appointment(): void
    {
        $appointment = $this->makeAppointment(['estado' => 'completada']);

        $response = $this->withToken($this->clientToken)->putJson("/api/v1/appointments/{$appointment->getAttribute('code')}", [
            'barber_id' => (string) $this->barber->id,
            'service_id' => (string) $this->service->id,
            'fecha' => now()->addDays(7)->toDateString(),
            'hora_inicio' => '14:00',
        ]);

        $response->assertStatus(422);
        $this->assertSame('completada', $appointment->fresh()->estado);
    }

    public function test_client_cannot_reschedule_someone_elses_appointment(): void
    {
        $otherClientUser = User::create(['name' => 'Otro Cliente', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $otherClient = Client::create(['user_id' => (string) $otherClientUser->id, 'telefono' => '5550003333']);
        $appointment = $this->makeAppointment(['client_id' => (string) $otherClient->id, 'estado' => 'pendiente']);

        $response = $this->withToken($this->clientToken)->putJson("/api/v1/appointments/{$appointment->getAttribute('code')}", [
            'barber_id' => (string) $this->barber->id,
            'service_id' => (string) $this->service->id,
            'fecha' => now()->addDays(7)->toDateString(),
            'hora_inicio' => '14:00',
        ]);

        $response->assertStatus(403);
    }

    public function test_client_cannot_cancel_within_the_policy_window(): void
    {
        $appointment = $this->makeAppointment([
            'fecha' => now()->toDateString(),
            'hora_inicio' => now()->addHours(2)->format('H:i:00'),
            'estado' => 'confirmada',
        ]);

        $response = $this->withToken($this->clientToken)->deleteJson("/api/v1/appointments/{$appointment->getAttribute('code')}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Solo puedes cancelar con al menos 24 horas de anticipación.');
        $this->assertSame('confirmada', $appointment->fresh()->estado);
    }

    public function test_client_can_cancel_outside_the_policy_window(): void
    {
        $appointment = $this->makeAppointment(['fecha' => now()->addDays(5)->toDateString(), 'estado' => 'confirmada']);

        $response = $this->withToken($this->clientToken)->deleteJson("/api/v1/appointments/{$appointment->getAttribute('code')}");

        $response->assertOk();
        $this->assertSame('cancelada', $appointment->fresh()->estado);
    }

    public function test_client_sees_only_own_payments_and_can_access_own_receipt(): void
    {
        $ownAppointment = $this->makeAppointment(['estado' => 'completada']);
        $ownPayment = Payment::create([
            'appointment_id' => (string) $ownAppointment->id,
            'monto' => 200,
            'metodo_pago' => 'efectivo',
            'propina' => 20,
        ]);

        $otherClientUser = User::create(['name' => 'Otro Cliente Pagos', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $otherClient = Client::create(['user_id' => (string) $otherClientUser->id, 'telefono' => '5550004444']);
        $otherAppointment = $this->makeAppointment(['client_id' => (string) $otherClient->id, 'estado' => 'completada']);
        $otherPayment = Payment::create([
            'appointment_id' => (string) $otherAppointment->id,
            'monto' => 300,
            'metodo_pago' => 'efectivo',
            'propina' => 0,
        ]);

        $response = $this->withToken($this->clientToken)->getJson('/api/v1/payments');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownPayment->id)
            ->assertJsonPath('meta.total_pagado', 220);

        $this->withToken($this->clientToken)->getJson("/api/v1/payments/{$ownPayment->id}/receipt")->assertOk();
        $this->withToken($this->clientToken)->getJson("/api/v1/payments/{$otherPayment->id}/receipt")->assertStatus(403);
    }
}
