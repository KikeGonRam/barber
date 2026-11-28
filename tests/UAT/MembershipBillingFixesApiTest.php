<?php

namespace Tests\UAT;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\MembershipPlan;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Services\Payment\StripePaymentService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesMembershipData;
use Tests\TestCase;

/**
 * Cobros de membresía: borrar una cuenta deja de cobrarla en Stripe, y anular un cobro
 * no deja el precio rebajado como base del siguiente.
 */
class MembershipBillingFixesApiTest extends TestCase
{
    use CreatesMembershipData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        $this->cleanMembershipData();

        parent::tearDown();
    }

    /** @return array{0: User, 1: Client, 2: ClientMembership} */
    private function member(int $descuentoPct = 10): array
    {
        $user = $this->userWithRole('cliente');
        $client = $this->clientFor($user);
        $plan = MembershipPlan::create([
            'nombre' => 'Plan', 'precio_mensual' => 199, 'descuento_pct' => $descuentoPct,
            'stripe_product_id' => 'prod_test', 'stripe_price_id' => 'price_test', 'activo' => true,
        ]);
        $membership = ClientMembership::create([
            'client_id' => (string) $client->id, 'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test', 'stripe_subscription_id' => 'sub_member',
            'estado' => ClientMembership::ESTADO_ACTIVA, 'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false, 'periodo_actual_fin' => now()->addWeeks(2),
        ]);

        return [$user, $client, $membership];
    }

    private function corteDeMembresia(): Service
    {
        return Service::create(['nombre' => 'Corte Membresia', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
    }

    public function test_deleting_own_account_cancels_the_stripe_subscription(): void
    {
        [$user, , $membership] = $this->member();
        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('cancelSubscriptionNow')->once()->with('sub_member');
        });

        $this->withToken($this->tokenFor($user))
            ->deleteJson('/api/v1/profile', ['password' => 'password'])
            ->assertOk();

        $this->assertNull(User::find($user->id));
        $this->assertSame(ClientMembership::ESTADO_CANCELADA, $membership->fresh()->getAttribute('estado'));
        $this->assertFalse($membership->fresh()->getAttribute('bloquea_membresia'));
    }

    public function test_the_account_is_kept_when_stripe_cannot_cancel(): void
    {
        [$user, , $membership] = $this->member();
        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('cancelSubscriptionNow')->andThrow(new \RuntimeException('Stripe no responde'));
        });

        $this->withToken($this->tokenFor($user))
            ->deleteJson('/api/v1/profile', ['password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('membresia');

        $this->assertNotNull(User::find($user->id));
        $this->assertSame(1, MobileApiToken::where('user_id', (string) $user->id)->count());
        $this->assertSame(ClientMembership::ESTADO_ACTIVA, $membership->fresh()->getAttribute('estado'));
    }

    public function test_an_admin_deleting_a_member_cancels_the_subscription(): void
    {
        [$user, , $membership] = $this->member();
        $admin = $this->userWithRole('administrador');
        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('cancelSubscriptionNow')->once()->with('sub_member');
        });

        $this->withToken($this->tokenFor($admin))->deleteJson("/api/v1/users/{$user->id}")->assertOk();

        $this->assertSame(ClientMembership::ESTADO_CANCELADA, $membership->fresh()->getAttribute('estado'));
    }

    public function test_charging_again_after_annulling_applies_the_discount_only_once(): void
    {
        Notification::fake();
        Storage::fake('receipts');
        [, $client] = $this->member(descuentoPct: 10);
        $admin = $this->userWithRole('administrador');
        $appointment = $this->appointmentFor($client, $this->corteDeMembresia());
        $charge = ['appointment_id' => (string) $appointment->id, 'metodo_pago' => 'efectivo'];

        $first = app(PaymentService::class)->create($charge, (string) $admin->id);
        $this->assertEquals(180.0, (float) $first->monto);

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/payments/{$first->id}", ['motivo' => 'Se cobró con el método equivocado'])
            ->assertOk();

        $second = app(PaymentService::class)->create($charge, (string) $admin->id);

        $this->assertEquals(180.0, (float) $second->monto);
    }

    public function test_the_price_is_cleared_before_the_appointment_can_be_charged_again(): void
    {
        [, $client] = $this->member();
        $admin = $this->userWithRole('administrador');
        $appointment = $this->appointmentFor($client, $this->corteDeMembresia());
        $appointment->update(['precio_cobrado' => 180]);
        $payment = Payment::create([
            'appointment_id' => (string) $appointment->id, 'monto' => 180, 'metodo_pago' => 'efectivo', 'propina' => 0,
            'estado' => Payment::ESTADO_VERIFICADO,
        ]);
        $seguiaBloqueada = null;
        Appointment::updating(function () use ($payment, &$seguiaBloqueada) {
            $seguiaBloqueada = Payment::find($payment->getKey()) !== null;
        });

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/payments/{$payment->getKey()}", ['motivo' => 'Se cobró con el método equivocado'])
            ->assertOk();

        $this->assertTrue($seguiaBloqueada, 'El precio debe limpiarse mientras el cobro aún bloquea la cita.');
    }

    public function test_annulling_a_deposit_keeps_the_charged_price(): void
    {
        [, $client] = $this->member();
        $admin = $this->userWithRole('administrador');
        $appointment = $this->appointmentFor($client, $this->corteDeMembresia());
        $appointment->update(['precio_cobrado' => 180]);
        $deposit = Payment::create([
            'appointment_id' => (string) $appointment->id, 'monto' => 90, 'metodo_pago' => 'tarjeta', 'propina' => 0,
            'estado' => Payment::ESTADO_VERIFICADO, 'es_deposito' => true,
        ]);

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/payments/{$deposit->id}", ['motivo' => 'Anticipo registrado dos veces'])
            ->assertOk();

        $this->assertEquals(180.0, (float) $appointment->fresh()->precio_cobrado);
    }
}
