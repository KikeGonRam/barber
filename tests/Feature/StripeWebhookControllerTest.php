<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Appointment\AppointmentNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cubre StripeWebhookController (Fase A del plan Stripe): antes de esta
 * fase, zero cobertura de tests en todo el flujo de Stripe pese a que mueve
 * dinero real. Cubre: firma inválida, conciliación exitosa con monto
 * recalculado en servidor (nunca el que venga en el payload de Stripe),
 * idempotencia (pago ya registrado no duplica ni truena), y los tres
 * eventos nuevos agregados en esta misma fase (payment_intent.payment_failed
 * ahora sí avisa a staff en vez de solo loggear; charge.refunded y
 * charge.dispute.created, que antes ni se procesaban).
 */
class StripeWebhookControllerTest extends TestCase
{
    private string $webhookSecret = 'whsec_test_secret_for_tests';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => $this->webhookSecret]);
    }

    protected function tearDown(): void
    {
        Payment::query()->delete();
        Appointment::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function makeChargeableAppointment(): Appointment
    {
        $barberUser = User::create(['name' => 'Barbero Stripe', 'email' => 'barbero-stripe@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Stripe', 'activo' => true]);
        $clientUser = User::create(['name' => 'Cliente Stripe', 'email' => 'cliente-stripe@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0]);
        $service = Service::create(['nombre' => 'Corte clásico', 'precio' => 300, 'duracion_min' => 30, 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'confirmada',
        ]);
    }

    private function signedHeader(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->webhookSecret);

        return "t={$timestamp},v1={$signature}";
    }

    private function postWebhook(array $event)
    {
        $payload = json_encode($event);

        return $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE-SIGNATURE' => $this->signedHeader($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = json_encode(['id' => 'evt_x', 'type' => 'payment_intent.succeeded', 'data' => ['object' => []]]);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE-SIGNATURE' => 't='.time().',v1=not-a-real-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);
        $this->assertSame(0, Payment::count());
    }

    public function test_succeeded_event_creates_payment_with_server_computed_amount(): void
    {
        $appointment = $this->makeChargeableAppointment();

        // El monto en el payload de Stripe (999999, absurdamente distinto
        // del precio real del servicio) nunca debe usarse -- solo está
        // aquí para probar que el webhook lo ignora por completo.
        $response = $this->postWebhook([
            'id' => 'evt_succeeded_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_test_1',
                'amount' => 999999,
                'metadata' => ['appointment_id' => (string) $appointment->id, 'puntos_canjeados' => '0'],
            ]],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'appointment_id' => (string) $appointment->id,
            'monto' => 300.0,
            'metodo_pago' => 'tarjeta',
            'stripe_payment_id' => 'pi_test_1',
        ]);
    }

    public function test_succeeded_event_is_idempotent_when_payment_already_registered(): void
    {
        $appointment = $this->makeChargeableAppointment();

        $event = [
            'id' => 'evt_succeeded_2',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_test_2',
                'metadata' => ['appointment_id' => (string) $appointment->id],
            ]],
        ];

        $this->postWebhook($event)->assertOk();
        $this->assertSame(1, Payment::where('appointment_id', (string) $appointment->id)->count());

        // Mismo evento otra vez (Stripe reintenta webhooks) -- no debe
        // duplicar el pago ni devolver un error al reintento.
        $this->postWebhook($event)->assertOk();
        $this->assertSame(1, Payment::where('appointment_id', (string) $appointment->id)->count());
    }

    public function test_failed_event_creates_no_payment_and_notifies_staff(): void
    {
        Notification::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Stripe', 'email' => 'admin-stripe@test.local', 'password' => 'password']);
        $admin->forceFill(['role_id' => [(string) $adminRole->id]])->save();

        $appointment = $this->makeChargeableAppointment();

        $response = $this->postWebhook([
            'id' => 'evt_failed_1',
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => [
                'id' => 'pi_test_failed',
                'metadata' => ['appointment_id' => (string) $appointment->id],
                'last_payment_error' => ['message' => 'Tarjeta rechazada'],
            ]],
        ]);

        $response->assertOk();
        $this->assertSame(0, Payment::count());
        Notification::assertSentTo($admin, AppointmentNotification::class, fn ($n) => $n->subject === 'Pago con tarjeta fallido');
    }

    public function test_refunded_event_does_not_throw_when_no_local_payment_matches(): void
    {
        // Un reembolso de un payment_intent que esta app no conoce
        // (p. ej. cobrado desde otra integración) no debe tronar.
        $response = $this->postWebhook([
            'id' => 'evt_refunded_unknown',
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_unknown',
                'payment_intent' => 'pi_unknown',
                'amount_refunded' => 30000,
            ]],
        ]);

        $response->assertOk();
    }

    public function test_refunded_event_is_processed_for_a_known_payment(): void
    {
        Notification::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Refund', 'email' => 'admin-refund@test.local', 'password' => 'password']);
        $admin->forceFill(['role_id' => [(string) $adminRole->id]])->save();

        $appointment = $this->makeChargeableAppointment();

        $this->postWebhook([
            'id' => 'evt_succeeded_for_refund',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_test_refund',
                'metadata' => ['appointment_id' => (string) $appointment->id],
            ]],
        ])->assertOk();

        $response = $this->postWebhook([
            'id' => 'evt_refunded_1',
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_test_refund',
                'payment_intent' => 'pi_test_refund',
                'amount_refunded' => 30000,
            ]],
        ]);

        $response->assertOk();
        // No revierte nada automáticamente todavía (alcance de esta fase:
        // solo avisar) -- el Payment y la cita quedan intactos.
        $this->assertSame(1, Payment::where('stripe_payment_id', 'pi_test_refund')->count());
        Notification::assertSentTo($admin, AppointmentNotification::class, fn ($n) => $n->subject === 'Reembolso de Stripe');
    }

    public function test_dispute_created_event_does_not_throw_when_no_local_payment_matches(): void
    {
        $response = $this->postWebhook([
            'id' => 'evt_dispute_unknown',
            'type' => 'charge.dispute.created',
            'data' => ['object' => [
                'id' => 'dp_unknown',
                'payment_intent' => 'pi_unknown',
                'reason' => 'fraudulent',
            ]],
        ]);

        $response->assertOk();
    }

    public function test_unhandled_event_types_are_a_noop(): void
    {
        $response = $this->postWebhook([
            'id' => 'evt_unhandled',
            'type' => 'customer.created',
            'data' => ['object' => ['id' => 'cus_test']],
        ]);

        $response->assertOk();
        $this->assertSame(0, Payment::count());
    }
}
