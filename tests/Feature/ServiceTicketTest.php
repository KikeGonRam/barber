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
use App\Notifications\Payment\PaymentReceiptNotification;
use App\Services\Payment\PaymentService;
use App\Services\Payment\ServiceTicketService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Flujo de citas V2, etapa 4: el ticket (correo con comprobante y factura + pantalla) sale al terminar el servicio, con
 * cualquier método de pago, y una sola vez.
 */
class ServiceTicketTest extends TestCase
{
    private const HOY = '2026-10-08';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::HOY.' 10:00:00');
        Storage::fake('receipts');
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
        $barber = Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Barbero Ticket', 'activo' => true]);

        return [$barber, $token];
    }

    /** @return array{0: Client, 1: User, 2: string} */
    private function clientWithToken(string $email): array
    {
        $token = $this->tokenFor('cliente', $email, $user);
        $client = Client::create(['user_id' => (string) $user->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        return [$client, $user, $token];
    }

    private function appointment(Barber $barber, Client $client, string $estado = 'confirmada'): Appointment
    {
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => self::HOY,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => $estado,
        ]);
    }

    private function charge(Appointment $appointment, string $metodo = 'efectivo'): Payment
    {
        return app(PaymentService::class)->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 200,
            'metodo_pago' => $metodo,
            'propina' => 20,
        ], (string) Str::uuid());
    }

    private function setStatus(string $token, Appointment $appointment, string $estado): TestResponse
    {
        return $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$appointment->getAttribute('code').'/status', ['estado' => $estado]);
    }

    private function finish(string $token, Appointment $appointment): TestResponse
    {
        $this->setStatus($token, $appointment, 'en_proceso')->assertOk();

        return $this->setStatus($token, $appointment, 'completada');
    }

    // ---- Cuándo sale el ticket -----------------------------------------------------------------------------------------

    public function test_cobrar_antes_de_iniciar_no_manda_el_ticket_todavia(): void
    {
        Notification::fake();
        [$barber] = $this->barberWithToken('tk1@test.local');
        [$client, $clientUser] = $this->clientWithToken('tkc1@test.local');
        $cita = $this->appointment($barber, $client);

        $payment = $this->charge($cita);

        Notification::assertNotSentTo($clientUser, PaymentReceiptNotification::class);
        $this->assertNotEmpty($payment->comprobante_pdf, 'el comprobante ya está generado');
        $this->assertNull($payment->fresh()->getAttribute('ticket_enviado_en'));
    }

    public function test_al_terminar_el_servicio_sale_el_ticket_una_sola_vez_y_se_muestra_en_pantalla(): void
    {
        Notification::fake();
        [$barber, $token] = $this->barberWithToken('tk2@test.local');
        [$client, $clientUser] = $this->clientWithToken('tkc2@test.local');
        $cita = $this->appointment($barber, $client);
        $payment = $this->charge($cita, 'tarjeta');

        $response = $this->finish($token, $cita)->assertOk();

        Notification::assertSentToTimes($clientUser, PaymentReceiptNotification::class, 1);
        $this->assertNotNull($payment->fresh()->getAttribute('ticket_enviado_en'));

        $ticket = $response->json('ticket');
        $this->assertSame('Corte', $ticket['servicio']);
        $this->assertSame('tarjeta', $ticket['metodo_pago']);
        $this->assertEquals(200.0, (float) $ticket['monto']);
        $this->assertEquals(20.0, (float) $ticket['propina']);
        $this->assertEquals(220.0, (float) $ticket['total_pagado']);
        $this->assertStringStartsWith('F-', $ticket['folio']);
        $this->assertNotEmpty($ticket['comprobante_url']);

        // Reintentar la emisión no lo manda dos veces.
        app(ServiceTicketService::class)->issueOnCompletion($cita->fresh(), null);
        Notification::assertSentToTimes($clientUser, PaymentReceiptNotification::class, 1);
    }

    public function test_cobrar_un_servicio_en_proceso_manda_el_ticket_de_inmediato_y_no_se_repite(): void
    {
        Notification::fake();
        [$barber] = $this->barberWithToken('tk3@test.local');
        [$client, $clientUser] = $this->clientWithToken('tkc3@test.local');
        $cita = $this->appointment($barber, $client, 'en_proceso');

        $this->charge($cita);
        Notification::assertSentToTimes($clientUser, PaymentReceiptNotification::class, 1);

        app(ServiceTicketService::class)->issueOnCompletion($cita->fresh(), null);
        Notification::assertSentToTimes($clientUser, PaymentReceiptNotification::class, 1);
    }

    public function test_transferencia_verificada_tambien_recibe_el_ticket_al_terminar(): void
    {
        Notification::fake();
        Queue::fake();
        [$barber, $token] = $this->barberWithToken('tk4@test.local');
        [$client, $clientUser] = $this->clientWithToken('tkc4@test.local');
        $cita = $this->appointment($barber, $client);
        $service = app(PaymentService::class);

        $pending = $service->uploadTransferReceipt($cita, UploadedFile::fake()->create('c.jpg', 10, 'image/jpeg'), (string) $clientUser->id);
        $service->approveTransfer($pending, (string) Str::uuid());
        Notification::assertNotSentTo($clientUser, PaymentReceiptNotification::class);

        $this->finish($token, $cita)->assertOk();

        Notification::assertSentToTimes($clientUser, PaymentReceiptNotification::class, 1);
    }

    public function test_pagar_el_servicio_completo_al_reservar_genera_el_cobro_final_y_el_ticket(): void
    {
        Notification::fake();
        [$barber, $token] = $this->barberWithToken('tk5@test.local');
        [$client, $clientUser] = $this->clientWithToken('tkc5@test.local');
        $cita = $this->appointment($barber, $client);
        Payment::create(['appointment_id' => (string) $cita->id, 'monto' => 200, 'metodo_pago' => 'tarjeta', 'propina' => 0, 'estado' => Payment::ESTADO_VERIFICADO, 'es_deposito' => true]);

        $response = $this->finish($token, $cita)->assertOk();

        $final = Payment::where('appointment_id', (string) $cita->id)->where('es_deposito', '!=', true)->first();
        $this->assertNotNull($final, 'se registra el cobro final neto');
        $this->assertEquals(0.0, (float) $final->monto);
        $this->assertSame('tarjeta', $final->metodo_pago);
        Notification::assertSentToTimes($clientUser, PaymentReceiptNotification::class, 1);

        $ticket = $response->json('ticket');
        $this->assertEquals(200.0, (float) $ticket['deposito_aplicado']);
        $this->assertEquals(200.0, (float) $ticket['total_pagado']);
        $this->assertEquals(0.0, (float) $ticket['descuentos']);
    }

    public function test_un_deposito_parcial_se_muestra_como_aplicado_en_el_ticket(): void
    {
        Notification::fake();
        [$barber, $token] = $this->barberWithToken('tk6@test.local');
        [$client] = $this->clientWithToken('tkc6@test.local');
        $cita = $this->appointment($barber, $client);
        Payment::create(['appointment_id' => (string) $cita->id, 'monto' => 50, 'metodo_pago' => 'tarjeta', 'propina' => 0, 'estado' => Payment::ESTADO_VERIFICADO, 'es_deposito' => true]);
        app(PaymentService::class)->create(['appointment_id' => (string) $cita->id, 'monto' => 150, 'metodo_pago' => 'efectivo', 'propina' => 0], (string) Str::uuid());

        $ticket = $this->finish($token, $cita)->json('ticket');

        $this->assertEquals(50.0, (float) $ticket['deposito_aplicado']);
        $this->assertEquals(150.0, (float) $ticket['monto']);
        $this->assertEquals(200.0, (float) $ticket['total_pagado']);
    }

    // ---- Consultar el ticket ---------------------------------------------------------------------------------------------

    public function test_quien_puede_ver_el_ticket(): void
    {
        Notification::fake();
        [$barber, $barberToken] = $this->barberWithToken('tk7@test.local');
        [, , $otherClientToken] = $this->clientWithToken('tkc7b@test.local');
        [$client, , $clientToken] = $this->clientWithToken('tkc7@test.local');
        $recepcion = $this->tokenFor('recepcionista', 'tkrec7@test.local');
        $cita = $this->appointment($barber, $client);
        $this->charge($cita);
        $this->finish($barberToken, $cita)->assertOk();
        $url = '/api/v1/appointments/'.$cita->getAttribute('code').'/ticket';

        foreach ([$clientToken, $barberToken, $recepcion] as $token) {
            $this->withToken($token)->getJson($url)->assertOk()->assertJsonPath('data.servicio', 'Corte');
        }
        $this->withToken($otherClientToken)->getJson($url)->assertForbidden();
        $this->assertContains($this->getJson($url)->status(), [401, 403], 'sin sesión no se ve el ticket');
    }

    public function test_sin_servicio_terminado_no_hay_ticket(): void
    {
        Notification::fake();
        [$barber, $barberToken] = $this->barberWithToken('tk8@test.local');
        [$client] = $this->clientWithToken('tkc8@test.local');
        $cita = $this->appointment($barber, $client);
        $this->charge($cita);

        $this->withToken($barberToken)
            ->getJson('/api/v1/appointments/'.$cita->getAttribute('code').'/ticket')
            ->assertNotFound();
    }
}
