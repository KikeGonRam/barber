<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Appointment\AppointmentNotification;
use App\Notifications\Appointment\ServiceEndingNotification;
use App\Services\Push\PushRouting;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Flujo de citas V2, etapa 3: aviso al barbero 5 min antes del fin del servicio y agregar tiempo (+10/+15…) sin chocar
 * con la siguiente cita, avisando al cliente.
 */
class ServiceTimeTest extends TestCase
{
    private const HOY = '2026-10-08';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(self::HOY.' 09:56:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Appointment::withTrashed()->forceDelete();
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

    /** @return array{0: Barber, 1: string, 2: User} */
    private function barberWithToken(string $email): array
    {
        $token = $this->tokenFor('barbero', $email, $user);
        $barber = Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Barbero Tiempo', 'activo' => true]);

        return [$barber, $token, $user];
    }

    /** @return array{0: Client, 1: User} */
    private function client(string $email): array
    {
        $user = User::create(['name' => 'Cliente Tiempo', 'email' => $email, 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $user->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        return [$client, $user];
    }

    /**
     * Servicio de 30 min que empezó a las 09:30: termina a las 10:00 (ahora son las 09:56).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function inProgress(Barber $barber, Client $client, array $overrides = []): Appointment
    {
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        return Appointment::create(array_merge([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => self::HOY,
            'hora_inicio' => '09:30:00',
            'hora_fin' => '10:00:00',
            'estado' => 'en_proceso',
            'servicio_iniciado_en' => self::HOY.' 09:30:00',
        ], $overrides));
    }

    private function nextAppointment(Barber $barber, Client $client, string $hora): Appointment
    {
        $service = Service::create(['nombre' => 'Barba', 'precio' => 100, 'duracion_min' => 30, 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => self::HOY,
            'hora_inicio' => $hora,
            'hora_fin' => Carbon::parse(self::HOY.' '.$hora)->addMinutes(30)->format('H:i:s'),
            'estado' => 'confirmada',
        ]);
    }

    /** @param  array<string, mixed>  $body */
    private function extend(string $token, Appointment $appointment, array $body): TestResponse
    {
        return $this->withToken($token)->postJson('/api/v1/appointments/'.$appointment->getAttribute('code').'/extend', $body);
    }

    // ---- Aviso 5 minutos antes ---------------------------------------------------------------------------------------

    public function test_avisa_al_barbero_cuando_faltan_5_minutos_o_menos(): void
    {
        Notification::fake();
        [$barber, , $barberUser] = $this->barberWithToken('t1@test.local');
        [$client] = $this->client('c1@test.local');
        $cita = $this->inProgress($barber, $client);

        Artisan::call('appointments:notify-service-ending');

        Notification::assertSentTo($barberUser, ServiceEndingNotification::class, function (ServiceEndingNotification $n) use ($barberUser) {
            $data = $n->toArray($barberUser);

            return $data['type'] === 'service_ending' && $data['minutes_left'] === 4 && $data['appointment_code'] !== null;
        });
        $this->assertNotNull($cita->fresh()->getAttribute('aviso_fin_enviado_en'));
    }

    public function test_el_aviso_se_envia_una_sola_vez(): void
    {
        Notification::fake();
        [$barber, , $barberUser] = $this->barberWithToken('t2@test.local');
        [$client] = $this->client('c2@test.local');
        $this->inProgress($barber, $client);

        Artisan::call('appointments:notify-service-ending');
        Artisan::call('appointments:notify-service-ending');

        Notification::assertSentToTimes($barberUser, ServiceEndingNotification::class, 1);
    }

    public function test_no_avisa_si_falta_mucho_ni_si_ya_se_paso(): void
    {
        Notification::fake();
        [$barber, , $barberUser] = $this->barberWithToken('t3@test.local');
        [$client] = $this->client('c3@test.local');
        $this->inProgress($barber, $client);

        Carbon::setTestNow(self::HOY.' 09:40:00'); // faltan 20 min
        Artisan::call('appointments:notify-service-ending');
        Carbon::setTestNow(self::HOY.' 10:05:00'); // ya se pasó: lo cubre el aviso de tiempo excedido
        Artisan::call('appointments:notify-service-ending');

        Notification::assertNothingSentTo($barberUser);
    }

    public function test_solo_avisa_de_servicios_en_proceso(): void
    {
        Notification::fake();
        [$barber, , $barberUser] = $this->barberWithToken('t4@test.local');
        [$client] = $this->client('c4@test.local');
        $this->inProgress($barber, $client, ['estado' => 'confirmada', 'servicio_iniciado_en' => null]);

        Artisan::call('appointments:notify-service-ending');

        Notification::assertNothingSentTo($barberUser);
    }

    public function test_el_aviso_viaja_por_push_a_la_agenda_del_barbero(): void
    {
        [$channel, $route] = PushRouting::for('service_ending');

        $this->assertSame('operacion', $channel);
        $this->assertSame('barber_agenda', $route);
    }

    public function test_el_push_lleva_el_codigo_de_la_cita_y_los_botones(): void
    {
        [$barber, , $barberUser] = $this->barberWithToken('t13@test.local');
        [$client] = $this->client('c13@test.local');
        $cita = $this->inProgress($barber, $client);

        $payload = (new ServiceEndingNotification($cita, 4))->toWebPush($barberUser);

        $this->assertSame($cita->getAttribute('code'), $payload['appointment_code']);
        $this->assertSame('terminar,extender_10,extender_15', $payload['acciones']);
        $this->assertSame('service_ending', $payload['type']);
        $this->assertSame('barber_agenda', $payload['route']);
    }

    public function test_el_comando_esta_programado_cada_minuto(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('appointments:notify-service-ending', Artisan::output());
    }

    // ---- Agregar tiempo ----------------------------------------------------------------------------------------------

    public function test_el_barbero_agrega_tiempo_y_se_actualiza_el_fin_y_se_avisa_al_cliente(): void
    {
        Notification::fake();
        [$barber, $token] = $this->barberWithToken('t5@test.local');
        [$client, $clientUser] = $this->client('c5@test.local');
        $cita = $this->inProgress($barber, $client);
        Artisan::call('appointments:notify-service-ending');

        $response = $this->extend($token, $cita, ['minutos' => 10])->assertOk();

        $fresh = $cita->fresh();
        $this->assertSame(10, (int) $fresh->getAttribute('minutos_extra'));
        $this->assertSame('10:10:00', $fresh->hora_fin);
        $this->assertNull($fresh->getAttribute('aviso_fin_enviado_en'), 'el aviso se vuelve a armar para el nuevo fin');
        $this->assertSame(10, $response->json('data.minutos_extra'));
        $this->assertStringStartsWith(self::HOY.'T10:10:00', (string) $response->json('data.fin_estimado'));

        Notification::assertSentTo($clientUser, AppointmentNotification::class, fn ($n) => $n->toMail($clientUser)->subject === 'Tu servicio se extendió');
    }

    public function test_tras_extender_vuelve_a_avisar_antes_del_nuevo_fin(): void
    {
        Notification::fake();
        [$barber, $token, $barberUser] = $this->barberWithToken('t6@test.local');
        [$client] = $this->client('c6@test.local');
        $cita = $this->inProgress($barber, $client);

        Artisan::call('appointments:notify-service-ending');
        $this->extend($token, $cita, ['minutos' => 15])->assertOk(); // nuevo fin: 10:15

        Carbon::setTestNow(self::HOY.' 10:11:00');
        Artisan::call('appointments:notify-service-ending');

        Notification::assertSentToTimes($barberUser, ServiceEndingNotification::class, 2);
    }

    public function test_el_tiempo_extra_cuenta_para_el_fin_esperado(): void
    {
        [$barber] = $this->barberWithToken('t7@test.local');
        [$client] = $this->client('c7@test.local');
        $cita = $this->inProgress($barber, $client, ['minutos_extra' => 15]);

        $this->assertSame(self::HOY.' 10:15:00', $cita->expectedServiceEnd()?->format('Y-m-d H:i:s'));
    }

    public function test_no_extiende_si_choca_con_la_siguiente_cita_salvo_que_se_confirme(): void
    {
        Notification::fake();
        [$barber, $token] = $this->barberWithToken('t8@test.local');
        [$client] = $this->client('c8@test.local');
        [$siguiente, $siguienteUser] = $this->client('c8b@test.local');
        $cita = $this->inProgress($barber, $client);
        $this->nextAppointment($barber, $siguiente, '10:05:00');

        $bloqueada = $this->extend($token, $cita, ['minutos' => 10]);
        $bloqueada->assertStatus(422);
        $this->assertTrue($bloqueada->json('puede_forzar'));
        $this->assertSame('10:05', $bloqueada->json('choca_con.hora_inicio'));
        $this->assertSame(0, (int) $cita->fresh()->getAttribute('minutos_extra'));

        $this->extend($token, $cita, ['minutos' => 10, 'forzar' => true])->assertOk();
        $this->assertSame(10, (int) $cita->fresh()->getAttribute('minutos_extra'));

        Notification::assertSentTo($siguienteUser, AppointmentNotification::class, fn ($n) => $n->toMail($siguienteUser)->subject === 'Tu cita podría empezar un poco tarde');
    }

    public function test_una_cita_que_empieza_despues_del_nuevo_fin_no_es_conflicto(): void
    {
        Notification::fake();
        [$barber, $token] = $this->barberWithToken('t9@test.local');
        [$client] = $this->client('c9@test.local');
        [$siguiente] = $this->client('c9b@test.local');
        $cita = $this->inProgress($barber, $client);
        $this->nextAppointment($barber, $siguiente, '10:30:00');

        $this->extend($token, $cita, ['minutos' => 15])->assertOk();
    }

    public function test_valida_las_opciones_el_maximo_y_el_estado(): void
    {
        Notification::fake();
        [$barber, $token] = $this->barberWithToken('t10@test.local');
        [$client] = $this->client('c10@test.local');
        $cita = $this->inProgress($barber, $client);

        $this->extend($token, $cita, ['minutos' => 7])->assertStatus(422);

        $this->extend($token, $cita, ['minutos' => 30])->assertOk();
        $this->extend($token, $cita, ['minutos' => 30])->assertOk();
        $tope = $this->extend($token, $cita, ['minutos' => 10]);
        $tope->assertStatus(422);
        $this->assertStringContainsString('máximo', (string) $tope->json('message'));

        $confirmada = $this->inProgress($barber, $client, ['estado' => 'confirmada', 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'servicio_iniciado_en' => null]);
        $this->extend($token, $confirmada, ['minutos' => 10])->assertStatus(422);
    }

    public function test_quien_puede_agregar_tiempo(): void
    {
        Notification::fake();
        [$barber, $ownerToken] = $this->barberWithToken('t11@test.local');
        [, $otherToken] = $this->barberWithToken('t11b@test.local');
        [$client] = $this->client('c11@test.local');
        $recepcion = $this->tokenFor('recepcionista', 'rec11@test.local');
        $clienteToken = $this->tokenFor('cliente', 'cli11@test.local');
        $cita = $this->inProgress($barber, $client);

        $this->extend($otherToken, $cita, ['minutos' => 10])->assertForbidden();
        $this->extend($clienteToken, $cita, ['minutos' => 10])->assertForbidden();
        $this->extend($recepcion, $cita, ['minutos' => 10])->assertOk();
        $this->extend($ownerToken, $cita, ['minutos' => 10])->assertOk();
        $this->assertSame(20, (int) $cita->fresh()->getAttribute('minutos_extra'));
    }

    public function test_terminar_ya_es_completar_el_servicio(): void
    {
        Notification::fake();
        [$barber, $token] = $this->barberWithToken('t12@test.local');
        [$client] = $this->client('c12@test.local');
        $cita = $this->inProgress($barber, $client);

        $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$cita->getAttribute('code').'/status', ['estado' => 'completada'])
            ->assertOk();

        $this->assertSame('completada', $cita->fresh()->estado);
    }
}
