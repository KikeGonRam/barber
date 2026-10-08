<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/** urbanblade:mail-check y la advertencia de arranque cuando el correo no entrega nada. */
class MailCheckCommandTest extends TestCase
{
    public function test_envia_el_correo_de_prueba_y_muestra_la_configuracion_sin_la_contrasena(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'barberia@gmail.com',
            'mail.mailers.smtp.password' => 'secreto-que-no-debe-salir',
            'mail.from.address' => 'barberia@gmail.com',
        ]);
        $fake = new class
        {
            /** @var list<string> */
            public array $sent = [];

            public function raw(string $text, \Closure $callback): void
            {
                $message = new Message(new Email);
                $callback($message);
                $symfony = $message->getSymfonyMessage();
                $this->sent[] = $symfony->getSubject().'|'.$symfony->getTo()[0]->getAddress();
            }
        };
        Mail::swap($fake);

        $this->artisan('urbanblade:mail-check', ['email' => 'cliente@correo.com'])
            ->expectsOutputToContain('smtp.gmail.com')
            ->expectsOutputToContain('ba***@gmail.com')
            ->expectsOutputToContain('definida')
            ->doesntExpectOutputToContain('secreto-que-no-debe-salir')
            ->doesntExpectOutputToContain('NO se envían')
            ->assertSuccessful();

        $this->assertSame(['Prueba de correo — UrbanBlade|cliente@correo.com'], $fake->sent);
    }

    public function test_con_el_mailer_log_avisa_que_no_se_envia_a_nadie(): void
    {
        config(['mail.default' => 'log', 'mail.from.address' => 'hello@example.com']);

        $this->artisan('urbanblade:mail-check', ['email' => 'cliente@correo.com'])
            ->expectsOutputToContain('NO se envían')
            ->expectsOutputToContain('puede no llegar a nadie')
            ->assertSuccessful();
    }

    public function test_si_el_servidor_rechaza_el_envio_falla_con_el_motivo(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.gmail.com', 'mail.mailers.smtp.username' => 'barberia@gmail.com', 'mail.from.address' => 'barberia@gmail.com']);
        Mail::swap(new class
        {
            public function raw(string $text, \Closure $callback): void
            {
                throw new \RuntimeException('Username and Password not accepted');
            }
        });

        $this->artisan('urbanblade:mail-check', ['email' => 'cliente@correo.com'])
            ->expectsOutputToContain('Username and Password not accepted')
            ->assertFailed();
    }

    public function test_en_produccion_con_el_mailer_log_deja_una_advertencia_una_sola_vez_por_hora(): void
    {
        Cache::forget('mail-config-warning');
        config(['mail.default' => 'log', 'mail.from.address' => 'hello@example.com']);
        $this->app['env'] = 'production';
        Log::shouldReceive('warning')->once();

        $provider = new AppServiceProvider($this->app);
        $warn = new \ReflectionMethod($provider, 'warnIfMailIsNotDelivering');
        $warn->invoke($provider);
        $warn->invoke($provider);

        Cache::forget('mail-config-warning');
    }

    public function test_fuera_de_produccion_no_advierte(): void
    {
        Cache::forget('mail-config-warning');
        config(['mail.default' => 'log', 'mail.from.address' => 'hello@example.com']);
        Log::shouldReceive('warning')->never();

        $provider = new AppServiceProvider($this->app);
        (new \ReflectionMethod($provider, 'warnIfMailIsNotDelivering'))->invoke($provider);

        $this->addToAssertionCount(1);
    }
}
