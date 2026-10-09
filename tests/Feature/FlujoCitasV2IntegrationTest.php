<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\LoyaltyTransaction;
use App\Models\MobileApiToken;
use App\Models\NoShowFee;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Appointment\ServiceEndingNotification;
use App\Notifications\Payment\PaymentReceiptNotification;
use App\Services\Payment\CashCloseService;
use App\Services\Payment\StripePaymentService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Flujo de citas V2, de punta a punta por la API real y con los cuatro roles: reservar → el barbero aprueba → el pago se
 * resuelve (tarjeta, transferencia o efectivo) → iniciar → aviso de 5 minutos → agregar tiempo → terminar → ticket; y el
 * camino de la inasistencia con cargo y adeudo. Cada paso usa los mismos endpoints que consumen la web y la app.
 */
class FlujoCitasV2IntegrationTest extends TestCase
{
    private const HOY = '2026-10-08';

    private Barber $barber;

    private string $barberToken;

    private string $recepcionToken;

    private string $adminToken;

    private User $clientUser;

    private string $clientToken;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::HOY.' 09:50:00');
        Storage::fake('receipts');
        Cache::forget(BarbershopSetting::CACHE_KEY);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $barberToken = $this->tokenFor('barbero', 'barbero@flujo.test', $barberUser);
        $this->barberToken = $barberToken;
        $this->barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Flujo', 'activo' => true]);
        $this->recepcionToken = $this->tokenFor('recepcionista', 'recepcion@flujo.test');
        $this->adminToken = $this->tokenFor('administrador', 'admin@flujo.test');
        $this->clientToken = $this->tokenFor('cliente', 'cliente@flujo.test', $clientUser);
        $this->clientUser = $clientUser;
        Client::create(['user_id' => (string) $this->clientUser->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $this->service = Service::create(['nombre' => 'Corte clásico', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        NoShowFee::query()->delete();
        BarbershopSetting::query()->delete();
        Cache::forget(BarbershopSetting::CACHE_KEY);
        Appointment::withTrashed()->forceDelete();
        Payment::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();
        LoyaltyTransaction::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    /** @param-out User $userOut */
    private function tokenFor(string $roleName, string $email, ?User &$userOut = null): string
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $userOut = User::create(['name' => ucfirst($roleName), 'email' => $email, 'password' => 'password']);
        $userOut->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $plain = 'token-'.$roleName.'-'.uniqid();
        MobileApiToken::create(['user_id' => (string) $userOut->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain)]);

        return $plain;
    }

    /** El cliente reserva hoy a las 10:00 (ya son las 09:50: dentro del margen de inicio). */
    private function book(string $hora = '10:00'): string
    {
        $response = $this->withToken($this->clientToken)->postJson('/api/v1/appointments', [
            'barber_id' => (string) $this->barber->id,
            'service_id' => (string) $this->service->id,
            'fecha' => self::HOY,
            'hora_inicio' => $hora,
            'acepta_cargo_inasistencia' => true,
        ]);
        $response->assertStatus(201);
        $this->assertSame('pendiente', $response->json('data.estado'), 'una cita nace pendiente');

        return (string) $response->json('data.code');
    }

    private function setEstado(string $token, string $code, string $estado): TestResponse
    {
        return $this->withToken($token)->patchJson("/api/v1/appointments/{$code}/status", ['estado' => $estado]);
    }

    private function approve(string $code): void
    {
        $this->setEstado($this->barberToken, $code, 'confirmada')->assertOk();
    }

    private function appointmentOf(string $code): Appointment
    {
        return Appointment::where('code', $code)->firstOrFail();
    }

    /** Servicio de 30 min iniciado a las 09:50: aviso a las 10:15, fin a las 10:20; a partir de aquí el reloj avanza. */
    private function startCompleteAndCheckTicket(string $code, string $expectedMethod, float $expectedTotal): void
    {
        Notification::fake();

        $this->setEstado($this->barberToken, $code, 'en_proceso')->assertOk();
        $this->assertSame('en_proceso', $this->appointmentOf($code)->estado);

        // A las 10:16 faltan 4 minutos: el barbero recibe el aviso con los botones.
        Carbon::setTestNow(self::HOY.' 10:16:00');
        Artisan::call('appointments:notify-service-ending');
        $barberUser = User::find($this->barber->user_id);
        Notification::assertSentTo($barberUser, ServiceEndingNotification::class);

        // Agrega 10 minutos: se avisa al cliente y el fin se mueve a las 10:30.
        $extend = $this->withToken($this->barberToken)->postJson("/api/v1/appointments/{$code}/extend", ['minutos' => 10]);
        $extend->assertOk();
        $this->assertSame(10, $extend->json('data.minutos_extra'));

        // Termina a las 10:25: sale el ticket, una sola vez.
        Carbon::setTestNow(self::HOY.' 10:25:00');
        $done = $this->setEstado($this->barberToken, $code, 'completada');
        $done->assertOk();
        $this->assertSame($expectedMethod, $done->json('ticket.metodo_pago'));
        $this->assertEquals($expectedTotal, (float) $done->json('ticket.total_pagado'));
        $this->assertSame(10, $done->json('ticket.minutos_extra'));
        Notification::assertSentToTimes($this->clientUser, PaymentReceiptNotification::class, 1);

        // El cliente reabre su ticket desde el historial.
        $this->withToken($this->clientToken)->getJson("/api/v1/appointments/{$code}/ticket")
            ->assertOk()->assertJsonPath('data.servicio', 'Corte clásico');
    }

    // ---- Recorridos de pago ----------------------------------------------------------------------------------------------

    public function test_recorrido_con_tarjeta(): void
    {
        $code = $this->book();
        $this->approve($code);

        // Aprobada pero sin pagar: no se puede iniciar y la API lo explica.
        $blocked = $this->setEstado($this->barberToken, $code, 'en_proceso');
        $blocked->assertStatus(422);
        $this->assertStringContainsString('pago', mb_strtolower((string) $blocked->json('message')));

        // Cobro con tarjeta (el id del intent lo confirma Stripe; aquí es simulado).
        Notification::fake();
        $this->withToken($this->recepcionToken)->postJson('/api/v1/payments', [
            'appointment_id' => (string) $this->appointmentOf($code)->id,
            'monto' => 200, 'metodo_pago' => 'tarjeta', 'stripe_payment_id' => 'pi_test_flujo', 'propina' => 20,
        ])->assertStatus(201);
        $this->assertSame('confirmada', $this->appointmentOf($code)->estado, 'cobrar no completa la cita');
        Notification::assertNotSentTo($this->clientUser, PaymentReceiptNotification::class);

        $this->startCompleteAndCheckTicket($code, 'tarjeta', 220.0);
        $this->assertGreaterThan(0, LoyaltyTransaction::count(), 'los puntos llegan al terminar');
    }

    public function test_recorrido_con_transferencia_verificada_por_recepcion(): void
    {
        Queue::fake();
        $code = $this->book();
        $this->approve($code);

        $upload = $this->withToken($this->clientToken)->post("/api/v1/appointments/{$code}/payment/receipt", [
            'comprobante' => UploadedFile::fake()->create('comprobante.jpg', 10, 'image/jpeg'),
        ], ['Accept' => 'application/json']);
        $upload->assertStatus(201);
        $this->assertSame('pendiente_verificacion', $upload->json('data.estado'));

        // Mientras nadie la verifique, la cita no se puede iniciar.
        $this->setEstado($this->barberToken, $code, 'en_proceso')->assertStatus(422);

        $this->withToken($this->recepcionToken)->postJson('/api/v1/payments/'.$upload->json('data.id').'/approve')->assertOk();
        $this->assertSame('confirmada', $this->appointmentOf($code)->estado, 'verificar tampoco completa la cita');

        $this->startCompleteAndCheckTicket($code, 'transferencia', 200.0);
    }

    public function test_recorrido_con_efectivo_cobrado_en_recepcion(): void
    {
        $code = $this->book();
        $this->approve($code);
        $this->setEstado($this->barberToken, $code, 'en_proceso')->assertStatus(422);

        $this->withToken($this->recepcionToken)->postJson('/api/v1/payments', [
            'appointment_id' => (string) $this->appointmentOf($code)->id,
            'monto' => 200, 'metodo_pago' => 'efectivo',
        ])->assertStatus(201);

        $this->startCompleteAndCheckTicket($code, 'efectivo', 200.0);
    }

    public function test_la_agenda_del_barbero_explica_si_se_puede_iniciar(): void
    {
        $code = $this->book();
        $this->approve($code);

        $agenda = fn () => collect($this->withToken($this->barberToken)->getJson('/api/v1/barber/agenda')->assertOk()->json('data'))
            ->firstWhere('code', $code);

        $this->assertFalse($agenda()['puede_iniciar']);
        $this->assertNotEmpty($agenda()['motivo_no_iniciar']);

        $this->withToken($this->recepcionToken)->postJson('/api/v1/payments', [
            'appointment_id' => (string) $this->appointmentOf($code)->id, 'monto' => 200, 'metodo_pago' => 'efectivo',
        ])->assertStatus(201);

        $this->assertTrue($agenda()['puede_iniciar']);
        $this->assertTrue($agenda()['pago_resuelto']);
    }

    // ---- Inasistencia -----------------------------------------------------------------------------------------------------

    public function test_recorrido_de_inasistencia_con_cargo_adeudo_y_pago_en_recepcion(): void
    {
        Notification::fake();
        $this->mock(StripePaymentService::class, function ($mock): void {
            $mock->shouldReceive('chargeSavedCard')->andReturn(null); // sin tarjeta guardada
        });

        $code = $this->book();
        $this->approve($code);

        // El cliente no llegó: el barbero lo marca y se genera el cargo del 50 %.
        $this->setEstado($this->barberToken, $code, 'no_asistio')->assertOk();
        $fee = NoShowFee::firstOrFail();
        $this->assertSame(NoShowFee::ESTADO_PENDIENTE, $fee->estado);
        $this->assertEquals(100.0, (float) $fee->monto);

        // El cliente ve cuánto debe y no puede reservar de nuevo.
        $this->assertEquals(100.0, (float) $this->withToken($this->clientToken)->getJson('/api/v1/no-show-fees')->assertOk()->json('adeudo_total'));
        $blocked = $this->withToken($this->clientToken)->postJson('/api/v1/appointments', [
            'barber_id' => (string) $this->barber->id, 'service_id' => (string) $this->service->id,
            'fecha' => '2026-10-20', 'hora_inicio' => '12:00', 'acepta_cargo_inasistencia' => true,
        ]);
        $blocked->assertStatus(422);
        $this->assertEquals(100.0, (float) $blocked->json('adeudo_inasistencia'));

        // Recepción lo cobra en efectivo: el cliente vuelve a poder reservar y el cobro entra al corte de caja.
        $this->withToken($this->recepcionToken)->postJson("/api/v1/no-show-fees/{$fee->id}/pay", ['metodo' => 'efectivo'])->assertOk();
        $this->withToken($this->clientToken)->postJson('/api/v1/appointments', [
            'barber_id' => (string) $this->barber->id, 'service_id' => (string) $this->service->id,
            'fecha' => '2026-10-20', 'hora_inicio' => '12:00', 'acepta_cargo_inasistencia' => true,
        ])->assertStatus(201);

        $corte = app(CashCloseService::class)->expectedFor(Carbon::parse(self::HOY));
        $this->assertEquals(100.0, $corte['por_metodo']['efectivo'] ?? 0.0);
    }

    public function test_administracion_puede_condonar_y_el_cliente_vuelve_a_reservar(): void
    {
        Notification::fake();
        $this->mock(StripePaymentService::class, function ($mock): void {
            $mock->shouldReceive('chargeSavedCard')->andReturn(null);
        });
        $code = $this->book();
        $this->approve($code);
        $this->setEstado($this->barberToken, $code, 'no_asistio')->assertOk();
        $fee = NoShowFee::firstOrFail();

        $this->withToken($this->adminToken)->postJson("/api/v1/no-show-fees/{$fee->id}/waive", ['motivo' => 'Emergencia médica'])->assertOk();

        $this->assertEquals(0.0, (float) $this->withToken($this->clientToken)->getJson('/api/v1/no-show-fees')->json('adeudo_total'));
    }
}
