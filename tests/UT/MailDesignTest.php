<?php

namespace Tests\UT;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Appointment\AppointmentNotification;
use App\Notifications\Auth\EmailVerificationCodeNotification;
use App\Services\Mail\ShopBranding;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** HT-25: correos con diseño cuidado y datos reales (sin direcciones de ejemplo, códigos legibles, vista previa). */
class MailDesignTest extends TestCase
{
    protected function tearDown(): void
    {
        BarbershopSetting::query()->delete();
        Cache::forget(BarbershopSetting::CACHE_KEY);

        parent::tearDown();
    }

    private function user(): User
    {
        return new User(['name' => 'Luis Prueba', 'email' => 'cliente@urbanblade.test']);
    }

    private function confirmacion(): MailMessage
    {
        $cita = (new Appointment(['fecha' => Carbon::parse('2026-11-27'), 'hora_inicio' => '16:00', 'hora_fin' => '16:45']))
            ->setRelation('service', new Service(['nombre' => 'Corte clásico', 'duracion_min' => 45]))
            ->setRelation('barber', (new Barber)->setRelation('user', new User(['name' => 'Mario Pérez'])));

        return (new AppointmentNotification($cita, 'Cita confirmada', 'Tu cita está confirmada', 'Te esperamos.', attachCalendar: true))
            ->toMail($this->user());
    }

    private function ics(MailMessage $mail): string
    {
        return (string) collect($mail->rawAttachments)->firstWhere('name', 'urbanblade-cita.ics')['data'];
    }

    public function test_el_calendario_de_la_cita_no_lleva_la_direccion_de_ejemplo_si_no_hay_una_configurada(): void
    {
        $mail = $this->confirmacion();
        $html = (string) $mail->render();

        $this->assertStringNotContainsString('Reforma', $html);
        $this->assertStringNotContainsString('Reforma', $this->ics($mail));
        $this->assertStringNotContainsString('LOCATION:', $this->ics($mail));
    }

    public function test_el_calendario_de_la_cita_usa_la_direccion_real_de_la_barberia(): void
    {
        BarbershopSetting::create(['nombre' => 'UrbanBlade', 'direccion' => 'Calle 5 de Mayo 12, Centro; Puebla']);
        Cache::forget(BarbershopSetting::CACHE_KEY);

        $mail = $this->confirmacion();

        // RFC 5545: la coma y el punto y coma van escapados en el .ics.
        $this->assertStringContainsString('LOCATION:Calle 5 de Mayo 12\, Centro\; Puebla', $this->ics($mail));
        $this->assertStringContainsString(rawurlencode('Calle 5 de Mayo 12, Centro; Puebla'), str_replace('+', '%20', (string) $mail->render()));
    }

    public function test_una_direccion_en_varias_lineas_no_abre_lineas_nuevas_en_el_ics(): void
    {
        BarbershopSetting::create(['nombre' => 'UrbanBlade', 'direccion' => "Calle 5 de Mayo 12\r\nATTENDEE:mailto:intruso@ejemplo.test"]);
        Cache::forget(BarbershopSetting::CACHE_KEY);

        $ics = $this->ics($this->confirmacion());

        $this->assertStringContainsString('LOCATION:Calle 5 de Mayo 12\nATTENDEE:mailto:intruso@ejemplo.test', $ics);
        $this->assertDoesNotMatchRegularExpression('/^ATTENDEE:/m', $ics);
    }

    public function test_la_fecha_de_la_cita_se_lee_en_espanol(): void
    {
        $this->assertSame('viernes 27 de noviembre', ShopBranding::fecha(Carbon::parse('2026-11-27')));
        $this->assertSame('27 de noviembre de 2026', ShopBranding::fecha(Carbon::parse('2026-11-27'), conAnio: true));
        $this->assertNull(ShopBranding::fecha(null));

        $this->assertStringContainsString('viernes 27 de noviembre', (string) $this->confirmacion()->render());
    }

    public function test_el_codigo_de_verificacion_va_en_un_bloque_grande_y_no_en_una_fila(): void
    {
        $html = (string) (new EmailVerificationCodeNotification('482915'))->toMail($this->user())->render();

        $this->assertMatchesRegularExpression('/class="code-box"[^>]*>\s*482915\s*</', $html);
        $this->assertStringNotContainsString('Código de verificación</td>', $html);
    }

    public function test_cada_correo_trae_texto_de_vista_previa_para_la_bandeja(): void
    {
        $html = (string) $this->confirmacion()->render();

        $this->assertMatchesRegularExpression('/class="preheader"[^>]*>\s*Te esperamos\.\s*</', $html);
    }

    public function test_la_vista_previa_genera_todos_los_tipos_de_correo(): void
    {
        $dir = storage_path('framework/testing/mail-previews-'.uniqid());

        try {
            $this->artisan('urbanblade:mail-preview', ['--dir' => $dir])->assertSuccessful();

            $archivos = collect(File::files($dir))->map->getFilename();
            $this->assertContains('index.html', $archivos);
            $this->assertGreaterThanOrEqual(21, $archivos->count() - 1);
            $this->assertStringContainsString('cita-confirmada.html', File::get($dir.'/index.html'));
            $this->assertStringNotContainsString('cid:', File::get($dir.'/cita-confirmada.html'));
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
