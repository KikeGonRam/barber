<?php

namespace Tests\Feature;

use App\Listeners\EmbedMailLogo;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Auth\WelcomeNotification;
use App\Services\Mail\ShopBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/** Diseño de correos y facturas: logo incrustado, datos reales de la barbería y marca en todos los correos. */
class MailBrandingTest extends TestCase
{
    private function user(): User
    {
        return new User(['name' => 'Luis Enrique', 'email' => 'cliente@correo.com']);
    }

    private function html(MailMessage $mail): string
    {
        return (string) $mail->render();
    }

    public function test_los_correos_referencian_el_logo_incrustado_y_no_uno_remoto(): void
    {
        $html = $this->html((new WelcomeNotification)->toMail($this->user()));

        $this->assertStringContainsString('cid:'.ShopBranding::LOGO_CID, $html);
        $this->assertStringNotContainsString('images/urbanblade-mark.png', $html);
    }

    public function test_el_correo_ya_no_trae_datos_de_ejemplo(): void
    {
        $html = $this->html((new WelcomeNotification)->toMail($this->user()));

        foreach (['Av. Reforma 123', '55 1234 5678', 'hola@urbanblade.com'] as $ejemplo) {
            $this->assertStringNotContainsString($ejemplo, $html);
        }
    }

    public function test_el_enlace_del_encabezado_va_al_sitio_web_y_no_a_la_api(): void
    {
        config(['app.url' => 'https://api.ejemplo.test', 'app.frontend_url' => 'https://sitio.ejemplo.test']);

        $html = $this->html((new WelcomeNotification)->toMail($this->user()));

        $this->assertStringContainsString('href="https://sitio.ejemplo.test"', $html);
        $this->assertStringNotContainsString('href="https://api.ejemplo.test"', $html);
    }

    public function test_restablecer_contrasena_sale_en_espanol_y_con_la_marca(): void
    {
        $mail = (new ResetPassword('token-de-prueba'))->toMail($this->user());
        $html = $this->html($mail);

        $this->assertSame('Restablece tu contraseña — UrbanBlade', $mail->subject);
        $this->assertStringContainsString('Restablece tu contraseña', $html);
        $this->assertStringContainsString('cid:'.ShopBranding::LOGO_CID, $html);
        $this->assertStringContainsString('reset-password?token=token-de-prueba', $html);
        $this->assertStringNotContainsString('Hello!', $html);
        $this->assertStringNotContainsString('Regards', $html);
    }

    public function test_el_listener_incrusta_el_logo_cuando_el_html_lo_referencia(): void
    {
        $email = (new Email)->from('a@b.com')->to('c@d.com')->subject('x')
            ->html('<p>Hola</p><img src="cid:'.ShopBranding::LOGO_CID.'">');

        (new EmbedMailLogo)->handle(new MessageSending($email));

        $partes = array_map(fn ($part): string => $part->getPreparedHeaders()->toString(), $email->getAttachments());
        $this->assertCount(1, $partes);
        $this->assertStringContainsString(ShopBranding::LOGO_CID, $partes[0]);
        $this->assertStringContainsString('image/png', $partes[0]);
        $this->assertStringContainsString('inline', $partes[0]);
    }

    public function test_el_listener_no_toca_los_correos_sin_logo(): void
    {
        $email = (new Email)->from('a@b.com')->to('c@d.com')->subject('x')->html('<p>Sin logo</p>');

        (new EmbedMailLogo)->handle(new MessageSending($email));

        $this->assertCount(0, $email->getAttachments());
    }

    public function test_los_datos_de_contacto_vienen_de_la_configuracion_y_se_omiten_si_faltan(): void
    {
        config(['mail.from.address' => 'hello@example.com']);

        $contact = ShopBranding::contact();

        $this->assertNull($contact['email'], 'un remitente de ejemplo no se muestra como contacto');
        $this->assertNotSame('', $contact['nombre']);

        config(['mail.from.address' => 'barberia@gmail.com']);
        $this->assertSame('barberia@gmail.com', ShopBranding::contact()['email']);
    }

    public function test_los_botones_de_los_correos_usan_el_enlace_inteligente(): void
    {
        config(['app.url' => 'https://api.ejemplo.test', 'app.frontend_url' => 'https://sitio.ejemplo.test']);

        $this->assertSame(
            'https://sitio.ejemplo.test/abrir?ruta=%2Fmy%2Finvoices',
            ShopBranding::smartLink('https://sitio.ejemplo.test/my/invoices'),
        );
        $this->assertSame(
            'https://sitio.ejemplo.test/abrir?ruta=%2Fdashboard',
            ShopBranding::smartLink('https://api.ejemplo.test/dashboard'),
        );

        $html = $this->html((new WelcomeNotification)->toMail($this->user()));
        $this->assertStringContainsString('https://sitio.ejemplo.test/abrir?ruta=%2Freservar', $html);
    }

    public function test_el_enlace_inteligente_no_toca_tokens_ni_enlaces_ajenos(): void
    {
        config(['app.url' => 'https://api.ejemplo.test', 'app.frontend_url' => 'https://sitio.ejemplo.test']);

        foreach ([
            'https://sitio.ejemplo.test/reset-password?token=abc&email=a%40b.com',
            'https://sitio.ejemplo.test/login',
            'https://api.ejemplo.test/track/click/123',
            'https://otro.test/my/invoices',
            'https://sitio.ejemplo.testevil.com/my/invoices',
            '',
        ] as $url) {
            $this->assertSame($url, ShopBranding::smartLink($url), "no debe cambiar: {$url}");
        }

        $this->assertStringNotContainsString('/abrir', $this->html((new ResetPassword('tok'))->toMail($this->user())));
    }

    public function test_el_logo_para_pdf_es_un_data_uri(): void
    {
        $this->assertStringStartsWith('data:image/png;base64,', ShopBranding::logoDataUri());
    }

    public function test_la_factura_en_pdf_lleva_el_logo_y_no_datos_de_ejemplo(): void
    {
        $html = view('pdf.invoice', [
            'folio' => 'F-ABC123', 'emitido' => '08/10/2026', 'cliente' => 'Luis Enrique', 'servicio' => 'Corte',
            'fecha' => '08/10/2026', 'barbero' => 'Ana', 'monto' => 250.0, 'propina' => 0.0, 'metodo' => 'Tarjeta',
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('Av. Reforma 123', $html);
        $this->assertStringStartsWith('%PDF', Pdf::loadHTML($html)->output());
    }

    public function test_el_comprobante_en_pdf_lleva_el_logo_y_no_datos_de_ejemplo(): void
    {
        $payment = new Payment(['monto' => 250, 'propina' => 0, 'metodo_pago' => 'tarjeta']);
        $payment->id = 'abcdef123456';

        $html = view('payments.receipt', ['payment' => $payment])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('+52 123 456 7890', $html);
    }

    public function test_el_recibo_de_pedido_en_pdf_lleva_el_logo_y_no_datos_de_ejemplo(): void
    {
        $html = view('pdf.order-receipt', [
            'folio' => 'P-ABC123', 'emitido' => '08/10/2026', 'cliente' => 'Luis Enrique',
            'items' => [['nombre' => 'Aceite', 'cantidad' => 1, 'precio' => 150, 'subtotal' => 150]], 'total' => 150.0, 'metodo' => 'Tarjeta',
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('Av. Reforma 123', $html);
    }
}
