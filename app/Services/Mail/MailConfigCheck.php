<?php

namespace App\Services\Mail;

/**
 * Señales de que el correo saliente no está listo para entregar mensajes de verdad. Sin ninguna variable
 * MAIL_* Laravel usa el mailer "log": la app responde "enviado" pero cada correo solo se escribe en el log
 * (pasó en staging hasta el 2026-10-08 y nadie lo notó porque no hay ningún error).
 */
final class MailConfigCheck
{
    /**
     * @return list<string> problemas encontrados; vacío si la configuración parece real
     */
    public static function problems(string $mailer, ?string $host, ?string $from, ?string $username): array
    {
        $problems = [];
        $from = trim((string) $from);

        if (in_array($mailer, ['log', 'array'], true)) {
            $problems[] = "MAIL_MAILER={$mailer}: los correos solo se escriben en el log (o en memoria) y NO se envían a nadie.";
        }

        if ($mailer === 'smtp' && in_array((string) $host, ['mailpit', 'mailhog', 'localhost', '127.0.0.1'], true)) {
            $problems[] = "MAIL_HOST={$host} es un buzón local de desarrollo: los correos no salen a internet.";
        }

        if ($from === '' || str_ends_with($from, '@example.com') || str_ends_with($from, '.test') || str_ends_with($from, '.local')) {
            $problems[] = 'MAIL_FROM_ADDRESS ('.($from === '' ? 'vacío' : $from).') no es un remitente real.';
        }

        if (str_contains((string) $host, 'gmail') && $username !== null && $username !== '' && $from !== '' && strcasecmp($from, $username) !== 0) {
            $problems[] = 'Con Gmail, MAIL_FROM_ADDRESS debe ser la misma cuenta que MAIL_USERNAME: Gmail reescribe el remitente a la cuenta autenticada.';
        }

        return $problems;
    }
}
