<?php

namespace Tests\Unit;

use App\Services\Mail\MailConfigCheck;
use PHPUnit\Framework\TestCase;

/** Cuándo el correo saliente NO está entregando mensajes de verdad. */
class MailConfigCheckTest extends TestCase
{
    public function test_el_mailer_log_es_un_problema(): void
    {
        $problems = MailConfigCheck::problems('log', null, 'no-reply@urbanblade.com.mx', null);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('NO se envían', $problems[0]);
    }

    public function test_el_valor_por_defecto_de_laravel_sin_variables_mail_se_detecta_completo(): void
    {
        // Lo que tenía staging: sin MAIL_*, mailer "log" y remitente hello@example.com.
        $problems = MailConfigCheck::problems('log', null, 'hello@example.com', null);

        $this->assertCount(2, $problems);
    }

    public function test_un_buzon_local_de_desarrollo_no_cuenta_como_entrega_real(): void
    {
        $problems = MailConfigCheck::problems('smtp', 'mailpit', 'no-reply@urbanblade.test', null);

        $this->assertCount(2, $problems);
    }

    public function test_gmail_bien_configurado_no_tiene_problemas(): void
    {
        $this->assertSame([], MailConfigCheck::problems('smtp', 'smtp.gmail.com', 'barberia@gmail.com', 'barberia@gmail.com'));
    }

    public function test_gmail_con_otro_remitente_avisa_que_lo_reescribe(): void
    {
        $problems = MailConfigCheck::problems('smtp', 'smtp.gmail.com', 'no-reply@urbanblade.com.mx', 'barberia@gmail.com');

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('reescribe el remitente', $problems[0]);
    }

    public function test_el_remitente_vacio_es_un_problema_y_la_comparacion_de_cuenta_no_distingue_mayusculas(): void
    {
        $this->assertCount(1, MailConfigCheck::problems('smtp', 'smtp.gmail.com', '', 'barberia@gmail.com'));
        $this->assertSame([], MailConfigCheck::problems('smtp', 'smtp.gmail.com', 'Barberia@Gmail.com', 'barberia@gmail.com'));
    }
}
