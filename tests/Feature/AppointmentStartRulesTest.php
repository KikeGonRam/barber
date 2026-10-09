<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\LoyaltyTransaction;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Appointment\AppointmentNotification;
use App\Services\Appointment\AppointmentNotifier;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Flujo de citas V2, etapa 1: iniciar el servicio solo el día de la cita y con el pago resuelto; cobrar una cita
 * confirmada ya no la completa (la termina el barbero); aprobación por barbero con respaldo de recepción/admin.
 */
class AppointmentStartRulesTest extends TestCase
{
    private const HOY = '2026-10-08';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::HOY.' 10:00:00');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
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

    private function tokenFor(string $roleName, string $email): string
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName), 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $plain = 'token-'.$roleName.'-'.uniqid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain)]);

        return $plain;
    }

    /** @return array{0: Barber, 1: string} */
    private function barberWithToken(string $email): array
    {
        $role = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Barbero Flujo', 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $barber = Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Barbero Flujo', 'activo' => true]);

        $plain = 'token-barbero-'.uniqid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain)]);

        return [$barber, $plain];
    }

    private function appointment(Barber $barber, string $estado = 'confirmada', string $fecha = self::HOY, string $hora = '10:00:00'): Appointment
    {
        $client = Client::create(['telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $fecha,
            'hora_inicio' => $hora,
            'hora_fin' => Carbon::parse($fecha.' '.$hora)->addMinutes(30)->format('H:i:s'),
            'estado' => $estado,
        ]);
    }

    private function pay(Appointment $appointment, string $estado, string $metodo = 'efectivo', ?bool $deposito = null, float $monto = 200): Payment
    {
        $data = [
            'appointment_id' => (string) $appointment->id,
            'monto' => $monto,
            'metodo_pago' => $metodo,
            'propina' => 0,
            'estado' => $estado,
        ];
        if ($deposito !== null) {
            $data['es_deposito'] = $deposito;
        }

        return Payment::create($data);
    }

    private function setStatus(string $token, Appointment $appointment, string $estado): TestResponse
    {
        return $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$appointment->getAttribute('code').'/status', ['estado' => $estado]);
    }

    private function start(string $token, Appointment $appointment): TestResponse
    {
        return $this->setStatus($token, $appointment, 'en_proceso');
    }

    // ---- Pago resuelto -------------------------------------------------------------------------------------------

    public function test_no_se_puede_iniciar_sin_pago_resuelto(): void
    {
        [$barber, $token] = $this->barberWithToken('b1@test.local');
        $appointment = $this->appointment($barber);

        $response = $this->start($token, $appointment);

        $response->assertStatus(422);
        $this->assertStringContainsString('pago', mb_strtolower((string) $response->json('message')));
        $this->assertSame('confirmada', $appointment->fresh()->estado);
    }

    public function test_se_puede_iniciar_con_efectivo_cobrado(): void
    {
        [$barber, $token] = $this->barberWithToken('b2@test.local');
        $appointment = $this->appointment($barber);
        $this->pay($appointment, Payment::ESTADO_VERIFICADO, 'efectivo');

        $this->start($token, $appointment)->assertOk();

        $fresh = $appointment->fresh();
        $this->assertSame('en_proceso', $fresh->estado);
        $this->assertNotNull($fresh->getAttribute('servicio_iniciado_en'));
    }

    public function test_se_puede_iniciar_con_tarjeta_cobrada(): void
    {
        [$barber, $token] = $this->barberWithToken('b3@test.local');
        $appointment = $this->appointment($barber);
        $this->pay($appointment, Payment::ESTADO_VERIFICADO, 'tarjeta');

        $this->start($token, $appointment)->assertOk();
    }

    public function test_transferencia_en_revision_no_cuenta_hasta_que_se_verifique(): void
    {
        [$barber, $token] = $this->barberWithToken('b4@test.local');
        $appointment = $this->appointment($barber);
        $payment = $this->pay($appointment, Payment::ESTADO_PENDIENTE_VERIFICACION, 'transferencia');

        $this->start($token, $appointment)->assertStatus(422);

        $payment->update(['estado' => Payment::ESTADO_VERIFICADO]);

        $this->start($token, $appointment)->assertOk();
    }

    public function test_transferencia_rechazada_no_cuenta(): void
    {
        [$barber, $token] = $this->barberWithToken('b5@test.local');
        $appointment = $this->appointment($barber);
        $this->pay($appointment, Payment::ESTADO_RECHAZADO, 'transferencia');

        $this->start($token, $appointment)->assertStatus(422);
    }

    public function test_un_deposito_parcial_no_resuelve_el_pago(): void
    {
        [$barber, $token] = $this->barberWithToken('b6@test.local');
        $appointment = $this->appointment($barber);
        $this->pay($appointment, Payment::ESTADO_VERIFICADO, 'tarjeta', true, 100);

        $this->start($token, $appointment)->assertStatus(422);
    }

    public function test_pagar_el_servicio_completo_al_reservar_resuelve_el_pago(): void
    {
        [$barber, $token] = $this->barberWithToken('b7@test.local');
        $appointment = $this->appointment($barber);
        $this->pay($appointment, Payment::ESTADO_VERIFICADO, 'tarjeta', true, 200);

        $this->start($token, $appointment)->assertOk();
    }

    // ---- Día y hora ----------------------------------------------------------------------------------------------

    public function test_no_se_puede_iniciar_una_cita_de_otro_dia(): void
    {
        [$barber, $token] = $this->barberWithToken('b8@test.local');
        $manana = $this->appointment($barber, 'confirmada', '2026-10-09');
        $this->pay($manana, Payment::ESTADO_VERIFICADO);
        $ayer = $this->appointment($barber, 'confirmada', '2026-10-07', '09:00:00');
        $this->pay($ayer, Payment::ESTADO_VERIFICADO);

        $this->start($token, $manana)->assertStatus(422);
        $this->start($token, $ayer)->assertStatus(422);
    }

    public function test_solo_se_puede_iniciar_desde_unos_minutos_antes_de_la_hora(): void
    {
        [$barber, $token] = $this->barberWithToken('b9@test.local');
        // «Ahora» es 10:00; margen por defecto 15 min.
        $tarde = $this->appointment($barber, 'confirmada', self::HOY, '12:00:00');
        $this->pay($tarde, Payment::ESTADO_VERIFICADO);
        $pronto = $this->appointment($barber, 'confirmada', self::HOY, '10:10:00');
        $this->pay($pronto, Payment::ESTADO_VERIFICADO);

        $response = $this->start($token, $tarde);
        $response->assertStatus(422);
        $this->assertStringContainsString('11:45', (string) $response->json('message'));

        $this->start($token, $pronto)->assertOk();
    }

    public function test_el_margen_es_configurable(): void
    {
        config(['appointments.start_margin_minutes' => 60]);
        [$barber, $token] = $this->barberWithToken('b10@test.local');
        $cita = $this->appointment($barber, 'confirmada', self::HOY, '10:50:00');
        $this->pay($cita, Payment::ESTADO_VERIFICADO);

        $this->start($token, $cita)->assertOk();
    }

    // ---- Quién puede / otros caminos ------------------------------------------------------------------------------

    public function test_recepcion_tambien_necesita_el_pago_para_iniciar(): void
    {
        [$barber] = $this->barberWithToken('b11@test.local');
        $token = $this->tokenFor('recepcionista', 'recep1@test.local');
        $cita = $this->appointment($barber);

        $this->start($token, $cita)->assertStatus(422);

        $this->pay($cita, Payment::ESTADO_VERIFICADO);
        $this->start($token, $cita)->assertOk();
    }

    public function test_la_edicion_completa_put_no_se_salta_la_regla(): void
    {
        [$barber] = $this->barberWithToken('b12@test.local');
        $token = $this->tokenFor('administrador', 'admin1@test.local');
        $cita = $this->appointment($barber);

        $response = $this->withToken($token)->putJson('/api/v1/appointments/'.$cita->getAttribute('code'), [
            'client_id' => (string) $cita->client_id,
            'barber_id' => (string) $cita->barber_id,
            'service_id' => (string) $cita->service_id,
            'fecha' => self::HOY,
            'hora_inicio' => '10:00',
            'estado' => 'en_proceso',
        ]);

        $response->assertStatus(422);
        $this->assertSame('confirmada', $cita->fresh()->estado);
    }

    public function test_la_api_informa_si_la_cita_se_puede_iniciar(): void
    {
        [$barber, $token] = $this->barberWithToken('b13@test.local');
        $cita = $this->appointment($barber);

        $sinPago = $this->listedAppointment($token, $cita);
        $this->assertFalse($sinPago['puede_iniciar']);
        $this->assertFalse($sinPago['pago_resuelto']);
        $this->assertNotEmpty($sinPago['motivo_no_iniciar']);

        $this->pay($cita, Payment::ESTADO_VERIFICADO);

        $conPago = $this->listedAppointment($token, $cita);
        $this->assertTrue($conPago['puede_iniciar']);
        $this->assertTrue($conPago['pago_resuelto']);
        $this->assertNull($conPago['motivo_no_iniciar']);
    }

    /** @return array<string, mixed> */
    private function listedAppointment(string $token, Appointment $cita): array
    {
        $response = $this->withToken($token)->getJson('/api/v1/appointments')->assertOk();
        $items = $response->json('data.data') ?? $response->json('data') ?? [];
        $item = collect($items)->firstWhere('code', $cita->getAttribute('code'));
        $this->assertIsArray($item, 'la cita debe aparecer en el listado');

        return $item;
    }

    // ---- Cobrar ya no completa la cita -----------------------------------------------------------------------------

    public function test_cobrar_una_cita_confirmada_no_la_completa_y_el_barbero_la_inicia_y_termina(): void
    {
        Notification::fake();
        Storage::fake('receipts');
        [$barber, $token] = $this->barberWithToken('b14@test.local');
        $cita = $this->appointment($barber);

        $payment = app(PaymentService::class)->create([
            'appointment_id' => (string) $cita->id,
            'monto' => 200,
            'metodo_pago' => 'efectivo',
            'propina' => 0,
        ], (string) Str::uuid());

        $fresh = $cita->fresh();
        $this->assertSame('confirmada', $fresh->estado, 'cobrar no debe completar la cita');
        $this->assertEquals(200, (float) $fresh->precio_cobrado);
        $this->assertNotEmpty($payment->comprobante_pdf, 'el comprobante se genera al cobrar');
        $this->assertSame(0, LoyaltyTransaction::count(), 'los puntos se dan al completar, no al cobrar');

        $this->start($token, $cita)->assertOk();
        $this->assertSame('en_proceso', $cita->fresh()->estado);

        $this->setStatus($token, $cita, 'completada')->assertOk();

        $this->assertSame('completada', $cita->fresh()->estado);
        $this->assertGreaterThan(0, LoyaltyTransaction::count(), 'al terminar el servicio se dan los puntos una sola vez');
    }

    public function test_cobrar_una_cita_ya_completada_conserva_el_comportamiento_anterior(): void
    {
        Notification::fake();
        Storage::fake('receipts');
        [$barber] = $this->barberWithToken('b15@test.local');
        $cita = $this->appointment($barber, 'completada');

        app(PaymentService::class)->create([
            'appointment_id' => (string) $cita->id,
            'monto' => 200,
            'metodo_pago' => 'efectivo',
            'propina' => 0,
        ], (string) Str::uuid());

        $this->assertSame('completada', $cita->fresh()->estado);
    }

    // ---- Aprobación ----------------------------------------------------------------------------------------------

    public function test_el_barbero_aprueba_su_cita_pendiente(): void
    {
        [$barber, $token] = $this->barberWithToken('b16@test.local');
        $cita = $this->appointment($barber, 'pendiente');

        $this->setStatus($token, $cita, 'confirmada')->assertOk();

        $this->assertSame('confirmada', $cita->fresh()->estado);
    }

    public function test_un_barbero_no_aprueba_la_cita_de_otro(): void
    {
        [$barberA] = $this->barberWithToken('b17a@test.local');
        [, $tokenB] = $this->barberWithToken('b17b@test.local');
        $cita = $this->appointment($barberA, 'pendiente');

        $this->setStatus($tokenB, $cita, 'confirmada')->assertForbidden();
    }

    public function test_recepcion_y_administracion_aprueban_como_respaldo(): void
    {
        [$barber] = $this->barberWithToken('b18@test.local');
        $recepcion = $this->tokenFor('recepcionista', 'recep2@test.local');
        $admin = $this->tokenFor('administrador', 'admin2@test.local');

        foreach ([[$recepcion, '11:00:00'], [$admin, '12:00:00']] as [$token, $hora]) {
            $cita = $this->appointment($barber, 'pendiente', self::HOY, $hora);
            $this->setStatus($token, $cita, 'confirmada')->assertOk();
            $this->assertSame('confirmada', $cita->fresh()->estado);
        }
    }

    public function test_el_cliente_no_puede_aprobar(): void
    {
        [$barber] = $this->barberWithToken('b19@test.local');
        $cliente = $this->tokenFor('cliente', 'cli1@test.local');
        $cita = $this->appointment($barber, 'pendiente');

        $this->setStatus($cliente, $cita, 'confirmada')->assertForbidden();
    }

    // ---- Avisos de la solicitud ------------------------------------------------------------------------------------

    public function test_una_cita_pendiente_no_se_anuncia_como_confirmada(): void
    {
        Notification::fake();
        [$barber] = $this->barberWithToken('b20@test.local');
        $cita = $this->appointment($barber, 'pendiente');
        $clientUser = User::create(['name' => 'Cliente Aviso', 'email' => 'cliente-aviso@test.local', 'password' => 'password']);
        $cita->client->update(['user_id' => (string) $clientUser->id]);

        app(AppointmentNotifier::class)->created($cita->fresh());

        Notification::assertSentTo($clientUser, AppointmentNotification::class, function ($notification) use ($clientUser) {
            return $notification->toMail($clientUser)->subject === 'Recibimos tu solicitud de cita';
        });
    }

    public function test_el_barbero_recibe_el_aviso_de_cita_por_aprobar(): void
    {
        Notification::fake();
        [$barber] = $this->barberWithToken('b21@test.local');
        $cita = $this->appointment($barber, 'pendiente');

        app(AppointmentNotifier::class)->created($cita->fresh());

        $barberUser = User::find($barber->user_id);
        Notification::assertSentTo($barberUser, AppointmentNotification::class, function ($notification) use ($barberUser) {
            return $notification->toMail($barberUser)->subject === 'Cita por aprobar';
        });
    }
}
