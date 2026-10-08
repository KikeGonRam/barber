<?php

namespace Tests\Unit;

use App\Services\Payment\StripePaymentService;
use PHPUnit\Framework\TestCase;
use Stripe\PaymentMethod;

/** Qué datos de una tarjeta guardada llegan a la app: el titular sí, el número completo nunca. */
class StripeCardPresenterTest extends TestCase
{
    private function method(array $overrides = []): PaymentMethod
    {
        return PaymentMethod::constructFrom(array_replace_recursive([
            'id' => 'pm_1',
            'object' => 'payment_method',
            'type' => 'card',
            'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2032, 'fingerprint' => 'abc', 'country' => 'US'],
            'billing_details' => ['name' => 'LUIS GONZALEZ', 'email' => 'secreto@correo.com'],
        ], $overrides));
    }

    public function test_incluye_el_titular_y_solo_los_datos_seguros(): void
    {
        $card = StripePaymentService::presentCard($this->method());

        $this->assertSame(['id' => 'pm_1', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2032, 'holder' => 'LUIS GONZALEZ'], $card);
        $this->assertStringNotContainsString('secreto@correo.com', json_encode($card));
        $this->assertStringNotContainsString('fingerprint', json_encode($card));
    }

    public function test_sin_nombre_el_titular_es_null(): void
    {
        $this->assertNull(StripePaymentService::presentCard($this->method(['billing_details' => ['name' => null]]))['holder']);
        $this->assertNull(StripePaymentService::presentCard($this->method(['billing_details' => ['name' => '   ']]))['holder']);
    }

    public function test_el_titular_se_limpia_y_se_recorta(): void
    {
        $this->assertSame('ANA LOPEZ', StripePaymentService::presentCard($this->method(['billing_details' => ['name' => "  ANA\nLOPEZ \t"]]))['holder']);
        $this->assertSame(40, mb_strlen((string) StripePaymentService::presentCard($this->method(['billing_details' => ['name' => str_repeat('A', 90)]]))['holder']));
    }
}
