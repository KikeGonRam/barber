<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\MembershipInvoice;
use App\Models\MembershipPlan;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Payment\StripePaymentService;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * GET /api/v1/memberships/invoices y /memberships/invoices/{invoice}/receipt-link: los cobros
 * mensuales de la membresía en "Mis facturas" del cliente, con el PDF de su factura de Stripe.
 */
class MembershipInvoicesApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        MembershipInvoice::query()->delete();
        ClientMembership::query()->delete();
        MembershipPlan::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    /** @return array{0: Client, 1: string} */
    private function clientWithToken(string $email): array
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente Membresía', 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $client = Client::create(['user_id' => (string) $user->id, 'nivel' => 'nuevo', 'puntos' => 0]);

        $plain = 'test-token-membresia-'.$user->id;
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain)]);

        return [$client, $plain];
    }

    private function invoiceFor(Client $client, float $monto, string $stripeInvoiceId): MembershipInvoice
    {
        $plan = MembershipPlan::create([
            'nombre' => 'Plan Oro', 'descripcion' => 'Descuento', 'precio_mensual' => $monto, 'descuento_pct' => 20,
            'stripe_product_id' => 'prod_test', 'stripe_price_id' => 'price_test', 'activo' => true,
        ]);
        $membership = ClientMembership::create([
            'client_id' => (string) $client->id, 'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test', 'stripe_subscription_id' => 'sub_'.$stripeInvoiceId,
            'estado' => ClientMembership::ESTADO_ACTIVA, 'bloquea_membresia' => true, 'cancelar_al_finalizar' => false,
        ]);

        return MembershipInvoice::create([
            'client_membership_id' => (string) $membership->id,
            'monto' => $monto,
            'stripe_invoice_id' => $stripeInvoiceId,
            'pagado_en' => now(),
        ]);
    }

    public function test_client_sees_only_their_own_membership_charges(): void
    {
        [$client, $token] = $this->clientWithToken('cliente-membresia-facturas@test.local');
        [$other] = $this->clientWithToken('otro-membresia-facturas@test.local');
        $own = $this->invoiceFor($client, 299, 'in_propia');
        $this->invoiceFor($other, 499, 'in_ajena');

        $response = $this->withToken($token)->getJson('/api/v1/memberships/invoices');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $own->id)
            ->assertJsonPath('data.0.plan', 'Plan Oro');
        $this->assertSame(299.0, (float) $response->json('meta.total_pagado'));
    }

    public function test_client_gets_the_stripe_pdf_only_for_their_own_charge(): void
    {
        [$client, $token] = $this->clientWithToken('cliente-membresia-pdf@test.local');
        [$other, $otherToken] = $this->clientWithToken('otro-membresia-pdf@test.local');
        $invoice = $this->invoiceFor($client, 299, 'in_pdf');
        $this->mock(StripePaymentService::class, fn ($mock) => $mock->shouldReceive('invoicePdfUrl')->once()->with('in_pdf')->andReturn('https://pay.stripe.test/in_pdf.pdf'));

        $this->withToken($token)->getJson("/api/v1/memberships/invoices/{$invoice->id}/receipt-link")
            ->assertOk()
            ->assertJsonPath('data.receipt_url', 'https://pay.stripe.test/in_pdf.pdf');

        $this->withToken($otherToken)->getJson("/api/v1/memberships/invoices/{$invoice->id}/receipt-link")
            ->assertForbidden();
    }

    private function pendingMembershipFor(Client $client): ClientMembership
    {
        $plan = MembershipPlan::create([
            'nombre' => 'Básico', 'descripcion' => '10% de descuento', 'precio_mensual' => 199, 'descuento_pct' => 10,
            'stripe_product_id' => 'prod_b', 'stripe_price_id' => 'price_b', 'activo' => true,
        ]);

        return ClientMembership::create([
            'client_id' => (string) $client->id, 'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_b', 'stripe_subscription_id' => 'sub_pendiente_b',
            'estado' => ClientMembership::ESTADO_PENDIENTE, 'bloquea_membresia' => true, 'cancelar_al_finalizar' => false,
        ]);
    }

    public function test_mine_activates_a_paid_pending_membership_even_if_the_webhook_never_arrived(): void
    {
        [$client, $token] = $this->clientWithToken('cliente-concilia@test.local');
        $membership = $this->pendingMembershipFor($client);
        $finPeriodo = now()->addMonth()->timestamp;
        $this->mock(StripePaymentService::class, fn ($mock) => $mock->shouldReceive('subscriptionSnapshot')->twice()->with('sub_pendiente_b')->andReturn([
            'status' => 'active', 'cancel_at_period_end' => false, 'current_period_end' => $finPeriodo,
            'invoice' => ['id' => 'in_primero_b', 'status' => 'paid', 'amount_paid' => 19900, 'paid_at' => now()->timestamp],
        ]));

        $this->withToken($token)->getJson('/api/v1/memberships/mine')
            ->assertOk()
            ->assertJsonPath('data.estado', ClientMembership::ESTADO_ACTIVA);

        $this->assertSame(ClientMembership::ESTADO_ACTIVA, $membership->fresh()->getAttribute('estado'));
        $this->assertSame(1, MembershipInvoice::where('client_membership_id', (string) $membership->id)->count());
        $this->assertSame(199.0, (float) MembershipInvoice::first()->getAttribute('monto'));

        // Consultar de nuevo antes de que la membresía quede vigente no duplica la factura.
        $membership->update(['periodo_actual_fin' => null]);
        $this->withToken($token)->getJson('/api/v1/memberships/mine')->assertOk();
        $this->assertSame(1, MembershipInvoice::where('client_membership_id', (string) $membership->id)->count());
    }

    public function test_mine_does_not_call_stripe_for_an_active_membership_within_its_period(): void
    {
        [$client, $token] = $this->clientWithToken('cliente-vigente@test.local');
        $membership = $this->pendingMembershipFor($client);
        $membership->update(['estado' => ClientMembership::ESTADO_ACTIVA, 'periodo_actual_fin' => now()->addDays(20)]);
        $this->mock(StripePaymentService::class, fn ($mock) => $mock->shouldReceive('subscriptionSnapshot')->never());

        $this->withToken($token)->getJson('/api/v1/memberships/mine')
            ->assertOk()
            ->assertJsonPath('data.estado', ClientMembership::ESTADO_ACTIVA);
    }

    public function test_mine_still_answers_when_stripe_is_down(): void
    {
        [$client, $token] = $this->clientWithToken('cliente-stripe-caido@test.local');
        $this->pendingMembershipFor($client);
        $this->mock(StripePaymentService::class, fn ($mock) => $mock->shouldReceive('subscriptionSnapshot')->once()->andThrow(new \RuntimeException('Stripe no responde')));

        $this->withToken($token)->getJson('/api/v1/memberships/mine')
            ->assertOk()
            ->assertJsonPath('data.estado', ClientMembership::ESTADO_PENDIENTE);
    }
}
