<?php

namespace Tests\Feature;

use App\Exceptions\Domain\MembershipException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\MembershipInvoice;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Service;
use App\Services\Membership\MembershipService;
use App\Services\Payment\CashCloseService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\StripePaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Membresía recurrente (roadmap P1, la última pieza del roadmap de mercado,
 * ver MembershipService). Cubre contratar/cancelar, la sincronización de
 * estado desde los webhooks de Stripe, el descuento (siempre el mayor entre
 * nivel y membresía, nunca sumados) y su inclusión en el corte de caja.
 */
class MembershipServiceTest extends TestCase
{
    private MembershipService $memberships;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberships = app(MembershipService::class);
    }

    protected function tearDown(): void
    {
        MembershipInvoice::query()->delete();
        ClientMembership::query()->delete();
        MembershipPlan::query()->delete();
        Payment::query()->delete();
        Appointment::withTrashed()->forceDelete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();

        parent::tearDown();
    }

    private function makeClient(string $nivel = 'nuevo'): Client
    {
        return Client::create(['telefono' => (string) random_int(5550000000, 5559999999), 'nivel' => $nivel, 'puntos' => 0, 'total_citas' => 0]);
    }

    private function makePlan(int $descuentoPct = 20, bool $activo = true): MembershipPlan
    {
        return MembershipPlan::create([
            'nombre' => 'Plan Oro',
            'descripcion' => 'Descuento en todos los servicios',
            'precio_mensual' => 299,
            'descuento_pct' => $descuentoPct,
            'stripe_product_id' => 'prod_test_123',
            'stripe_price_id' => 'price_test_123',
            'activo' => $activo,
        ]);
    }

    public function test_subscribe_creates_a_pending_membership_and_returns_client_secret(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();

        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('createCustomer')->once()->andReturn('cus_test_123');
            $mock->shouldReceive('createSubscription')->once()->andReturn([
                'subscription_id' => 'sub_test_123',
                'client_secret' => 'pi_test_secret_123',
            ]);
        });

        $result = app(MembershipService::class)->subscribe($client, $plan);

        $this->assertSame('pi_test_secret_123', $result['client_secret']);
        $this->assertSame(ClientMembership::ESTADO_PENDIENTE, $result['membership']->estado);
        $this->assertSame('sub_test_123', $result['membership']->stripe_subscription_id);
        $this->assertSame('cus_test_123', $client->fresh()->stripe_customer_id);
    }

    public function test_subscribe_reuses_an_existing_stripe_customer_id(): void
    {
        $client = $this->makeClient();
        $client->update(['stripe_customer_id' => 'cus_existing_456']);
        $plan = $this->makePlan();

        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('createCustomer')->never();
            $mock->shouldReceive('createSubscription')->once()->withArgs(fn ($customerId) => $customerId === 'cus_existing_456')->andReturn([
                'subscription_id' => 'sub_test_456',
                'client_secret' => 'pi_test_secret_456',
            ]);
        });

        app(MembershipService::class)->subscribe($client, $plan);
    }

    public function test_subscribe_rejects_an_inactive_plan(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan(activo: false);

        $this->expectException(MembershipException::class);
        $this->memberships->subscribe($client, $plan);
    }

    public function test_subscribe_rejects_a_second_membership_while_one_blocks(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();

        ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_existing',
            'estado' => ClientMembership::ESTADO_ACTIVA,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false,
        ]);

        $this->expectException(MembershipException::class);
        $this->memberships->subscribe($client, $plan);
    }

    public function test_subscribe_is_allowed_again_after_a_previous_membership_was_cancelled(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();

        ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_old',
            'estado' => ClientMembership::ESTADO_CANCELADA,
            'bloquea_membresia' => false,
            'cancelar_al_finalizar' => false,
        ]);

        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('createCustomer')->once()->andReturn('cus_test_789');
            $mock->shouldReceive('createSubscription')->once()->andReturn([
                'subscription_id' => 'sub_new',
                'client_secret' => 'pi_secret_new',
            ]);
        });

        $result = app(MembershipService::class)->subscribe($client, $plan);

        $this->assertSame(ClientMembership::ESTADO_PENDIENTE, $result['membership']->estado);
    }

    public function test_cancel_marks_cancelar_al_finalizar_without_changing_estado_yet(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();
        $membership = ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_active',
            'estado' => ClientMembership::ESTADO_ACTIVA,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false,
        ]);

        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('cancelSubscriptionAtPeriodEnd')->once()->with('sub_active');
        });

        $updated = app(MembershipService::class)->cancel($membership);

        $this->assertTrue($updated->cancelar_al_finalizar);
        $this->assertSame(ClientMembership::ESTADO_ACTIVA, $updated->estado);
    }

    public function test_cancel_rejects_an_already_cancelled_membership(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();
        $membership = ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_done',
            'estado' => ClientMembership::ESTADO_CANCELADA,
            'bloquea_membresia' => false,
            'cancelar_al_finalizar' => false,
        ]);

        $this->expectException(MembershipException::class);
        $this->memberships->cancel($membership);
    }

    public function test_cancel_rejects_a_duplicate_cancellation_request(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();
        $membership = ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_active',
            'estado' => ClientMembership::ESTADO_ACTIVA,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => true,
        ]);

        $this->expectException(MembershipException::class);
        $this->memberships->cancel($membership);
    }

    public function test_sync_from_stripe_status_activates_a_pending_membership(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();
        $membership = ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_pending',
            'estado' => ClientMembership::ESTADO_PENDIENTE,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false,
        ]);

        $periodoFin = Carbon::now()->addMonth();
        $this->memberships->syncFromStripeStatus('sub_pending', 'active', $periodoFin, false);

        $fresh = $membership->fresh();
        $this->assertSame(ClientMembership::ESTADO_ACTIVA, $fresh->estado);
        $this->assertTrue($fresh->bloquea_membresia);
        $this->assertEquals($periodoFin->toDateString(), $fresh->periodo_actual_fin->toDateString());
    }

    public function test_sync_from_stripe_status_marks_past_due_as_pago_fallido(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();
        $membership = ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_active',
            'estado' => ClientMembership::ESTADO_ACTIVA,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false,
        ]);

        $this->memberships->syncFromStripeStatus('sub_active', 'past_due', null, false);

        $this->assertSame(ClientMembership::ESTADO_PAGO_FALLIDO, $membership->fresh()->estado);
    }

    public function test_mark_cancelled_by_stripe_unblocks_future_subscriptions(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();
        $membership = ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_ending',
            'estado' => ClientMembership::ESTADO_ACTIVA,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => true,
        ]);

        $this->memberships->markCancelledByStripe('sub_ending');

        $fresh = $membership->fresh();
        $this->assertSame(ClientMembership::ESTADO_CANCELADA, $fresh->estado);
        $this->assertFalse($fresh->bloquea_membresia);
    }

    public function test_active_discount_for_returns_zero_without_a_membership(): void
    {
        $client = $this->makeClient();

        $this->assertSame(0, $this->memberships->activeDiscountFor($client));
    }

    public function test_active_discount_for_returns_zero_while_payment_is_failing(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan(descuentoPct: 25);
        ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_failing',
            'estado' => ClientMembership::ESTADO_PAGO_FALLIDO,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false,
        ]);

        $this->assertSame(0, $this->memberships->activeDiscountFor($client));
    }

    public function test_record_successful_invoice_is_idempotent(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();
        ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_active',
            'estado' => ClientMembership::ESTADO_ACTIVA,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false,
        ]);

        $this->memberships->recordSuccessfulInvoice('sub_active', 'in_test_123', 299.0, Carbon::now());
        $this->memberships->recordSuccessfulInvoice('sub_active', 'in_test_123', 299.0, Carbon::now());

        $this->assertSame(1, MembershipInvoice::count());
    }

    public function test_payment_service_applies_membership_discount_when_higher_than_nivel(): void
    {
        Notification::fake();

        // Nivel 'regular' da 5% de descuento (ver LoyaltyService::DISCOUNTS);
        // la membresía da 20%, así que debe ganar la membresía.
        $client = $this->makeClient('regular');
        $plan = $this->makePlan(descuentoPct: 20);
        ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_active',
            'estado' => ClientMembership::ESTADO_ACTIVA,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false,
        ]);

        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $payment = app(PaymentService::class)->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 200,
            'metodo_pago' => 'efectivo',
        ], null);

        // 200 - 20% = 160, no 200 - 5% = 190.
        $this->assertEquals(160.0, (float) $payment->monto);
    }

    public function test_payment_service_applies_nivel_discount_when_higher_than_membership(): void
    {
        Notification::fake();

        // Nivel 'leyenda' da 15%; la membresía solo da 5%, así que debe
        // ganar el nivel.
        $client = $this->makeClient('leyenda');
        $plan = $this->makePlan(descuentoPct: 5);
        ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_active',
            'estado' => ClientMembership::ESTADO_ACTIVA,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false,
        ]);

        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '11:00:00',
            'hora_fin' => '11:30:00',
            'estado' => 'confirmada',
        ]);

        $payment = app(PaymentService::class)->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 200,
            'metodo_pago' => 'efectivo',
        ], null);

        // 200 - 15% = 170, no 200 - 5% = 190.
        $this->assertEquals(170.0, (float) $payment->monto);
    }

    public function test_cash_close_includes_membership_invoices_as_tarjeta(): void
    {
        $client = $this->makeClient();
        $plan = $this->makePlan();
        ClientMembership::create([
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_active',
            'estado' => ClientMembership::ESTADO_ACTIVA,
            'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false,
        ]);

        $this->memberships->recordSuccessfulInvoice('sub_active', 'in_cash_close_test', 299.0, Carbon::today());

        $cashClose = app(CashCloseService::class);
        $expected = $cashClose->expectedFor(Carbon::today());

        $this->assertSame(1, $expected['membresias']);
        $this->assertEquals(299.0, $expected['por_metodo']['tarjeta']);
    }
}
