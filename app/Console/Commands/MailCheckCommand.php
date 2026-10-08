<?php

namespace App\Console\Commands;

use App\Services\Mail\MailConfigCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Manda un correo de prueba y dice qué proveedor y remitente se están usando, para comprobar la
 * configuración de correo sin crear cuentas ni pagos. Nunca imprime la contraseña.
 *
 *   php artisan urbanblade:mail-check correo@dominio.com
 */
class MailCheckCommand extends Command
{
    protected $signature = 'urbanblade:mail-check {email : Correo que recibe la prueba}';

    protected $description = 'Envía un correo de prueba y diagnostica la configuración de correo saliente';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $config = (array) config("mail.mailers.{$mailer}", []);
        $host = isset($config['host']) ? (string) $config['host'] : null;
        $username = isset($config['username']) ? (string) $config['username'] : null;
        $from = (string) config('mail.from.address');

        $this->table(['Configuración', 'Valor'], [
            ['Mailer (MAIL_MAILER)', $mailer],
            ['Servidor (MAIL_HOST)', $host ?? '—'],
            ['Puerto (MAIL_PORT)', (string) ($config['port'] ?? '—')],
            ['Esquema (MAIL_SCHEME)', (string) ($config['scheme'] ?? '(automático)')],
            ['Cuenta (MAIL_USERNAME)', $username !== null && $username !== '' ? self::mask($username) : 'sin definir'],
            ['Contraseña (MAIL_PASSWORD)', filled($config['password'] ?? null) ? 'definida' : 'sin definir'],
            ['Remitente (MAIL_FROM_ADDRESS)', $from],
        ]);

        $problems = MailConfigCheck::problems($mailer, $host, $from, $username);
        foreach ($problems as $problem) {
            $this->warn('⚠ '.$problem);
        }

        try {
            Mail::raw(
                "Prueba de correo de UrbanBlade.\n\nSi lees esto, el correo saliente funciona. Enviado el ".now()->format('d/m/Y H:i:s').'.',
                fn ($message) => $message->to((string) $this->argument('email'))->subject('Prueba de correo — UrbanBlade'),
            );
        } catch (\Throwable $e) {
            $this->error('No se pudo enviar: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($problems !== []) {
            $this->warn('El envío no falló, pero con esta configuración el correo puede no llegar a nadie.');

            return self::SUCCESS;
        }

        $this->info('Correo de prueba enviado a '.$this->argument('email').'. Revisa la bandeja (y spam).');

        return self::SUCCESS;
    }

    /** ab***@gmail.com: suficiente para reconocer la cuenta sin exponerla completa en logs. */
    private static function mask(string $value): string
    {
        [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');

        return $domain === '' ? substr($value, 0, 2).'***' : substr($local, 0, 2).'***@'.$domain;
    }
}
