<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\NoShowFee;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Appointment\AppointmentNotification;
use App\Services\Payment\CashCloseService;
use App\Services\Payment\NoShowFeeService;
use App\Services\Payment\StripePaymentService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Flujo de citas V2, etapa 2: cargo por inasistencia (50 % del servicio, configurable). Se cobra solo a la tarjeta
 * guardada; si no se puede queda como adeudo que bloquea nuevas reservas hasta cobrarlo en sucursal o condonarlo.
 */
class NoShowFeeTest extends TestCase
{
    private const HOY = '2026-10-08';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::HOY.' 12:00:00');
        Cache::forget(BarbershopSetting::CACHE_KEY);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
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

    /** @return array{0: Barber, 1: string} */
    private function barberWithToken(string $email): array
    {
        $token = $this->tokenFor('barbero', $email, $user);
        $barber = Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Barbero Cargo', 'activo' => true]);

        return [$barber, $token];
    }

    /**
     * Cliente con cuenta de usuario (para recibir avisos) y, si se pide, con rol cliente y token.
     *
     * @return array{0: Client, 1: User, 2: string|null}
     */
    private function client(string $email, bool $withToken = false): array
    {
        $token = null;
        if ($withToken) {
            $token = $this->tokenFor('cliente', $email, $user);
        } else {
            $user = User::create(['name' => 'Cliente Cargo', 'email' => $email, 'password' => 'password']);
        }
        $client = Client::create(['user_id' => (string) $user->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        return [$client, $user, $token];
    }

    private function appointment(Barber $barber, Client $client, string $estado = 'confirmada', string $fecha = self::HOY, string $hora = '10:00:00', float $precio = 200): Appointment
    {
        $service = Service::create(['nombre' => 'Corte', 'precio' => $precio, 'duracion_min' => 30, 'activo' => true]);

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

    private function markNoShow(string $token, Appointment $appointment): TestResponse
    {
        return $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$appointment->getAttribute('code').'/status', ['estado' => 'no_asistio']);
    }

    private function stripeReturns(?string $intentId): void
    {
        $this->mock(StripePaymentService::class, function ($mock) use ($intentId): void {
            $mock->shouldReceive('chargeSavedCard')->andReturn($intentId);
        });
    }

    // ---- Generación del cargo ------------------------------------------------------------------------------------

    public function test_marcar_no_asistio_genera_un_adeudo_del_50_por_ciento(): void
    {
        Notification::fake();
        $this->stripeReturns(null);
        [$barber, $token] = $this->barberWithToken('nb1@test.local');
        [$client, $clientUser] = $this->client('nc1@test.local');
        $cita = $this->appointment($barber, $client);

        $this->markNoShow($token, $cita)->assertOk();

        $fee = NoShowFee::where('appointment_id', (string) $cita->id)->firstOrFail();
        $this->assertSame(NoShowFee::ESTADO_PENDIENTE, $fee->estado);
        $this->assertEquals(100.0, (float) $fee->monto);
        $this->assertEquals(100.0, (float) $fee->monto_base);
        $this->assertSame(50, (int) $fee->porcentaje);

        Notification::assertSentTo($clientUser, AppointmentNotification::class, fn ($n) => $n->toMail($clientUser)->subject === 'Tienes un adeudo por inasistencia');
        Notification::assertNotSentTo($clientUser, AppointmentNotification::class, fn ($n) => $n->toMail($clientUser)->subject === 'Marcada como no asistió');
    }

    public function test_si_hay_tarjeta_guardada_se_cobra_sola(): void
    {
        Notification::fake();
        $this->stripeReturns('pi_cargo_123');
        [$barber, $token] = $this->barberWithToken('nb2@test.local');
        [$client, $clientUser] = $this->client('nc2@test.local');
        $cita = $this->appointment($barber, $client);

        $this->markNoShow($token, $cita)->assertOk();

        $fee = NoShowFee::where('appointment_id', (string) $cita->id)->firstOrFail();
        $this->assertSame(NoShowFee::ESTADO_PAGADO, $fee->estado);
        $this->assertSame('tarjeta', $fee->metodo_cobro);
        $this->assertSame('pi_cargo_123', $fee->stripe_payment_id);
        $this->assertSame(0.0, app(NoShowFeeService::class)->outstandingTotal($client));

        Notification::assertSentTo($clientUser, AppointmentNotification::class, fn ($n) => $n->toMail($clientUser)->subject === 'Cargo por inasistencia');
    }

    public function test_el_cargo_se_genera_una_sola_vez_por_cita(): void
    {
        Notification::fake();
        $this->stripeReturns(null);
        [$barber] = $this->barberWithToken('nb3@test.local');
        [$client] = $this->client('nc3@test.local');
        $cita = $this->appointment($barber, $client, 'no_asistio');

        $service = app(NoShowFeeService::class);
        $first = $service->assess($cita);
        $second = $service->assess($cita);

        $this->assertNotNull($first);
        $this->assertSame((string) $first->id, (string) $second?->id);
        $this->assertSame(1, NoShowFee::where('appointment_id', (string) $cita->id)->count());
    }

    public function test_solo_se_cobra_a_citas_marcadas_no_asistio(): void
    {
        [$barber] = $this->barberWithToken('nb4@test.local');
        [$client] = $this->client('nc4@test.local');
        $cita = $this->appointment($barber, $client, 'confirmada');

        $this->assertNull(app(NoShowFeeService::class)->assess($cita));
        $this->assertSame(0, NoShowFee::count());
    }

    public function test_el_porcentaje_es_configurable_y_cero_lo_desactiva(): void
    {
        Notification::fake();
        $this->stripeReturns(null);
        [$barber] = $this->barberWithToken('nb5@test.local');
        [$client] = $this->client('nc5@test.local');

        BarbershopSetting::create(['nombre' => 'UrbanBlade', 'comision_no_show_porcentaje' => 30]);
        Cache::forget(BarbershopSetting::CACHE_KEY);
        $a = app(NoShowFeeService::class)->assess($this->appointment($barber, $client, 'no_asistio'));
        $this->assertEquals(60.0, (float) $a?->monto);

        BarbershopSetting::query()->update(['comision_no_show_porcentaje' => 0]);
        Cache::forget(BarbershopSetting::CACHE_KEY);
        $b = app(NoShowFeeService::class)->assess($this->appointment($barber, $client, 'no_asistio', self::HOY, '11:00:00'));
        $this->assertNull($b);
    }

    public function test_un_pago_anticipado_se_descuenta_y_no_se_cobra_dos_veces(): void
    {
        Notification::fake();
        $this->stripeReturns(null);
        [$barber] = $this->barberWithToken('nb6@test.local');
        [$client] = $this->client('nc6@test.local');

        // Depósito retenido de $100 cubre el cargo de $100 completo.
        $cubierta = $this->appointment($barber, $client, 'no_asistio');
        Payment::create(['appointment_id' => (string) $cubierta->id, 'monto' => 100, 'metodo_pago' => 'tarjeta', 'propina' => 0, 'estado' => Payment::ESTADO_VERIFICADO, 'es_deposito' => true]);
        $fee = app(NoShowFeeService::class)->assess($cubierta);
        $this->assertNotNull($fee);
        $this->assertSame(NoShowFee::ESTADO_PAGADO, $fee->estado);
        $this->assertSame('anticipo', $fee->metodo_cobro);
        $this->assertEquals(0.0, (float) $fee->monto);

        // Depósito de $40: queda por cobrar la diferencia ($60).
        $parcial = $this->appointment($barber, $client, 'no_asistio', self::HOY, '11:00:00');
        Payment::create(['appointment_id' => (string) $parcial->id, 'monto' => 40, 'metodo_pago' => 'tarjeta', 'propina' => 0, 'estado' => Payment::ESTADO_VERIFICADO, 'es_deposito' => true]);
        $fee2 = app(NoShowFeeService::class)->assess($parcial);
        $this->assertNotNull($fee2);
        $this->assertSame(NoShowFee::ESTADO_PENDIENTE, $fee2->estado);
        $this->assertEquals(60.0, (float) $fee2->monto);
        $this->assertEquals(100.0, (float) $fee2->monto_base);
        $this->assertEquals(40.0, (float) $fee2->credito_anticipo);
    }

    public function test_el_proceso_automatico_no_cobra_cargo(): void
    {
        Notification::fake();
        [$barber] = $this->barberWithToken('nb7@test.local');
        [$client] = $this->client('nc7@test.local');
        $vencida = $this->appointment($barber, $client, 'confirmada', '2026-10-01');

        Artisan::call('appointments:mark-no-show');

        $this->assertSame('no_asistio', $vencida->fresh()->estado);
        $this->assertSame(0, NoShowFee::count(), 'solo una persona al marcar la inasistencia genera el cargo');
    }

    // ---- Adeudo bloquea reservar ---------------------------------------------------------------------------------

    /** @return array<string, string> */
    private function bookingPayload(Appointment $base, Client $client): array
    {
        return [
            'client_id' => (string) $client->id,
            'barber_id' => (string) $base->barber_id,
            'service_id' => (string) $base->service_id,
            'fecha' => '2026-10-20',
            'hora_inicio' => '12:00',
        ];
    }

    public function test_un_adeudo_pendiente_bloquea_nuevas_reservas_hasta_pagarlo(): void
    {
        Notification::fake();
        $this->stripeReturns(null);
        [$barber, $barberToken] = $this->barberWithToken('nb8@test.local');
        [$client] = $this->client('nc8@test.local');
        $admin = $this->tokenFor('administrador', 'nadmin8@test.local', $adminUser);
        $cita = $this->appointment($barber, $client);
        $this->markNoShow($barberToken, $cita)->assertOk();

        $bloqueada = $this->withToken($admin)->postJson('/api/v1/appointments', $this->bookingPayload($cita, $client));
        $bloqueada->assertStatus(422);
        $this->assertEquals(100.0, (float) $bloqueada->json('adeudo_inasistencia'));
        $this->assertStringContainsString('adeudo', mb_strtolower((string) $bloqueada->json('message')));

        $fee = NoShowFee::firstOrFail();
        app(NoShowFeeService::class)->markPaid($fee, 'efectivo', (string) $adminUser->id);

        $libre = $this->withToken($admin)->postJson('/api/v1/appointments', $this->bookingPayload($cita, $client));
        $this->assertStringNotContainsString('adeudo', mb_strtolower((string) $libre->json('message')));
    }

    public function test_aceptar_el_cargo_es_obligatorio_solo_si_esta_activado(): void
    {
        [$barber] = $this->barberWithToken('nb9@test.local');
        [$client, , $clientToken] = $this->client('nc9@test.local', true);
        $base = $this->appointment($barber, $client, 'completada');
        $payload = $this->bookingPayload($base, $client);
        unset($payload['client_id']);

        config(['appointments.require_no_show_acceptance' => false]);
        $sinExigir = $this->withToken((string) $clientToken)->postJson('/api/v1/appointments', $payload);
        $this->assertStringNotContainsString('aceptar el cargo', (string) $sinExigir->json('message'));

        config(['appointments.require_no_show_acceptance' => true]);
        $exigido = $this->withToken((string) $clientToken)->postJson('/api/v1/appointments', ['hora_inicio' => '13:00'] + $payload);
        $exigido->assertStatus(422);
        $this->assertStringContainsString('aceptar el cargo', (string) $exigido->json('message'));

        $aceptado = $this->withToken((string) $clientToken)->postJson('/api/v1/appointments', ['hora_inicio' => '14:00', 'acepta_cargo_inasistencia' => true] + $payload);
        $this->assertStringNotContainsString('aceptar el cargo', (string) $aceptado->json('message'));
        if ($aceptado->status() === 201) {
            $created = Appointment::where('hora_inicio', '14:00:00')->first();
            $this->assertNotNull($created?->getAttribute('cargo_inasistencia_aceptado_en'));
        }
    }

    // ---- API -------------------------------------------------------------------------------------------------------

    public function test_el_cliente_ve_sus_cargos_y_el_total_adeudado(): void
    {
        Notification::fake();
        $this->stripeReturns(null);
        [$barber] = $this->barberWithToken('nb10@test.local');
        [$client, , $clientToken] = $this->client('nc10@test.local', true);
        [$otro] = $this->client('nc10b@test.local');
        app(NoShowFeeService::class)->assess($this->appointment($barber, $client, 'no_asistio'));
        app(NoShowFeeService::class)->assess($this->appointment($barber, $otro, 'no_asistio', self::HOY, '11:00:00'));

        $response = $this->withToken((string) $clientToken)->getJson('/api/v1/no-show-fees')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(100.0, (float) $response->json('adeudo_total'));
        $this->assertSame((string) $client->id, $response->json('data.0.client_id'));
    }

    public function test_recepcion_ve_los_pendientes_y_los_cobra_en_sucursal(): void
    {
        Notification::fake();
        $this->stripeReturns(null);
        [$barber, $barberToken] = $this->barberWithToken('nb11@test.local');
        [$client] = $this->client('nc11@test.local');
        $recepcion = $this->tokenFor('recepcionista', 'nrec11@test.local');
        $this->markNoShow($barberToken, $this->appointment($barber, $client))->assertOk();
        $fee = NoShowFee::firstOrFail();

        $this->assertCount(1, $this->withToken($recepcion)->getJson('/api/v1/no-show-fees')->assertOk()->json('data'));

        $this->withToken($recepcion)->postJson('/api/v1/no-show-fees/'.$fee->id.'/pay', ['metodo' => 'efectivo'])->assertOk();
        $this->assertSame(NoShowFee::ESTADO_PAGADO, $fee->fresh()->estado);
        $this->assertSame('efectivo', $fee->fresh()->metodo_cobro);

        $this->withToken($recepcion)->postJson('/api/v1/no-show-fees/'.$fee->id.'/pay', ['metodo' => 'efectivo'])->assertStatus(422);
        $this->assertCount(0, $this->withToken($recepcion)->getJson('/api/v1/no-show-fees')->json('data'));
    }

    public function test_solo_administracion_condona_y_con_motivo(): void
    {
        Notification::fake();
        $this->stripeReturns(null);
        [$barber, $barberToken] = $this->barberWithToken('nb12@test.local');
        [$client] = $this->client('nc12@test.local');
        $recepcion = $this->tokenFor('recepcionista', 'nrec12@test.local');
        $admin = $this->tokenFor('administrador', 'nadmin12@test.local');
        $this->markNoShow($barberToken, $this->appointment($barber, $client))->assertOk();
        $fee = NoShowFee::firstOrFail();

        $this->withToken($recepcion)->postJson('/api/v1/no-show-fees/'.$fee->id.'/waive', ['motivo' => 'Emergencia'])->assertForbidden();
        $this->withToken($admin)->postJson('/api/v1/no-show-fees/'.$fee->id.'/waive', [])->assertStatus(422);
        $this->withToken($admin)->postJson('/api/v1/no-show-fees/'.$fee->id.'/waive', ['motivo' => 'Emergencia médica'])->assertOk();

        $this->assertSame(NoShowFee::ESTADO_CONDONADO, $fee->fresh()->estado);
        $this->assertSame('Emergencia médica', $fee->fresh()->motivo);
        $this->assertSame(0.0, app(NoShowFeeService::class)->outstandingTotal($client));
    }

    public function test_los_cargos_cobrados_entran_al_corte_de_caja_del_dia(): void
    {
        Notification::fake();
        $this->stripeReturns(null);
        [$barber] = $this->barberWithToken('nb14@test.local');
        [$client] = $this->client('nc14@test.local');

        // $100 cobrados en efectivo en sucursal hoy: suman al efectivo del día.
        $enEfectivo = app(NoShowFeeService::class)->assess($this->appointment($barber, $client, 'no_asistio'));
        $this->assertNotNull($enEfectivo);
        app(NoShowFeeService::class)->markPaid($enEfectivo, 'efectivo', (string) Str::uuid());

        // Cubierto por un depósito: ese dinero ya entró como Payment, no se cuenta dos veces.
        $cubierta = $this->appointment($barber, $client, 'no_asistio', self::HOY, '11:00:00');
        Payment::create(['appointment_id' => (string) $cubierta->id, 'monto' => 100, 'metodo_pago' => 'tarjeta', 'propina' => 0, 'estado' => Payment::ESTADO_VERIFICADO, 'es_deposito' => true]);
        app(NoShowFeeService::class)->assess($cubierta);

        // Pendiente: todavía no es dinero recibido.
        app(NoShowFeeService::class)->assess($this->appointment($barber, $client, 'no_asistio', self::HOY, '12:00:00'));

        $corte = app(CashCloseService::class)->expectedFor(Carbon::parse(self::HOY));

        $this->assertEquals(100.0, $corte['por_metodo']['efectivo'] ?? 0.0);
        $this->assertEquals(100.0, $corte['por_metodo']['tarjeta'] ?? 0.0, 'solo el depósito, una vez');
        $this->assertSame(1, $corte['cargos_inasistencia']);
    }

    public function test_un_barbero_no_gestiona_cargos(): void
    {
        [, $barberToken] = $this->barberWithToken('nb13@test.local');

        $this->withToken($barberToken)->getJson('/api/v1/no-show-fees')->assertForbidden();
    }
}
