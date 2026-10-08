<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\MembershipInvoice;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Membership\MembershipInvoiceNotification;
use App\Notifications\Order\OrderDeliveredNotification;
use App\Services\Membership\MembershipService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Los correos de compra llevan su comprobante o factura en PDF: pedido entregado (comprobante) y cada cobro
 * mensual de la membresía (factura, una sola vez por factura de Stripe).
 */
class InvoiceEmailsTest extends TestCase
{
    protected function tearDown(): void
    {
        MembershipInvoice::query()->delete();
        ClientMembership::query()->delete();
        MembershipPlan::query()->delete();
        Client::query()->delete();
        User::withTrashed()->forceDelete();

        parent::tearDown();
    }

    private function notifiable(): object
    {
        return new class
        {
            public string $name = 'Cliente';
        };
    }

    /** @return list<string> */
    private function attachmentNames(MailMessage $mail): array
    {
        return array_map(fn (array $a): string => $a['name'], $mail->rawAttachments);
    }

    public function test_el_correo_del_pedido_entregado_adjunta_el_comprobante(): void
    {
        $order = new Order([
            'folio' => 'P-ABC123',
            'total' => 300,
            'metodo_pago' => 'tarjeta',
            'items' => [['product_id' => 'p1', 'nombre' => 'Aceite de Barba', 'precio' => 150, 'cantidad' => 2, 'subtotal' => 300]],
        ]);

        $mail = (new OrderDeliveredNotification($order))->toMail($this->notifiable());

        $this->assertSame(['comprobante-P-ABC123.pdf'], $this->attachmentNames($mail));
        $this->assertStringStartsWith('%PDF', $mail->rawAttachments[0]['data']);
    }

    public function test_el_correo_de_la_membresia_adjunta_la_factura(): void
    {
        $invoice = new MembershipInvoice(['monto' => 299, 'stripe_invoice_id' => 'in_1abcdef', 'pagado_en' => now()]);

        $mail = (new MembershipInvoiceNotification($invoice, 'Básico'))->toMail($this->notifiable());

        $this->assertSame(['factura-M-ABCDEF.pdf'], $this->attachmentNames($mail));
        $this->assertSame('Tu factura de la membresía Básico', $mail->subject);
    }

    public function test_el_aviso_de_la_membresia_lleva_a_pagos_y_dice_el_monto(): void
    {
        $invoice = new MembershipInvoice(['monto' => 299.5, 'stripe_invoice_id' => 'in_1abcdef']);
        $payload = (new MembershipInvoiceNotification($invoice, 'Básico'))->toWebPush($this->notifiable());

        $this->assertSame('pagos', $payload['channel']);
        $this->assertSame('payments', $payload['route']);
        $this->assertStringContainsString('$299.50', $payload['body']);
    }

    public function test_cada_factura_de_stripe_avisa_una_sola_vez(): void
    {
        Notification::fake();
        $user = User::create(['name' => 'Cliente Membresía', 'email' => 'membresia-factura@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $user->id, 'telefono' => '5551230000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $plan = MembershipPlan::create(['nombre' => 'Básico', 'descripcion' => '10% de descuento', 'precio_mensual' => 199, 'descuento_pct' => 10, 'activo' => true]);
        ClientMembership::create([
            'client_id' => (string) $client->id, 'membership_plan_id' => (string) $plan->id, 'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_factura', 'estado' => ClientMembership::ESTADO_ACTIVA, 'bloquea_membresia' => true, 'cancelar_al_finalizar' => false,
        ]);
        $service = app(MembershipService::class);

        $service->recordSuccessfulInvoice('sub_factura', 'in_factura_1', 199.0, now());
        $service->recordSuccessfulInvoice('sub_factura', 'in_factura_1', 199.0, now());

        Notification::assertSentToTimes($user, MembershipInvoiceNotification::class, 1);
        Notification::assertSentTo($user, MembershipInvoiceNotification::class, fn (MembershipInvoiceNotification $n): bool => $n->planName === 'Básico');
    }

    public function test_un_cobro_de_membresia_sin_usuario_se_registra_sin_fallar(): void
    {
        Notification::fake();
        $client = Client::create(['telefono' => '5551239999', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $plan = MembershipPlan::create(['nombre' => 'Básico', 'descripcion' => 'x', 'precio_mensual' => 199, 'descuento_pct' => 10, 'activo' => true]);
        ClientMembership::create([
            'client_id' => (string) $client->id, 'membership_plan_id' => (string) $plan->id, 'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_sin_usuario', 'estado' => ClientMembership::ESTADO_ACTIVA, 'bloquea_membresia' => true, 'cancelar_al_finalizar' => false,
        ]);

        app(MembershipService::class)->recordSuccessfulInvoice('sub_sin_usuario', 'in_sin_usuario', 199.0, now());

        $this->assertSame(1, MembershipInvoice::count());
        Notification::assertNothingSent();
    }
}
