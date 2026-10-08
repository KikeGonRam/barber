<?php

namespace App\Listeners;

use App\Services\Mail\ShopBranding;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/**
 * Incrusta el logo dentro del propio correo (imagen «cid:») cuando el HTML lo referencia. Una imagen remota la
 * bloquean Gmail y otros clientes en remitentes nuevos («Mostrar imágenes»); una incrustada siempre se ve.
 * Funciona para todos los correos, incluidos los de Laravel (restablecer contraseña, verificación).
 */
class EmbedMailLogo
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        $html = $message->getHtmlBody();
        if (! is_string($html) || ! str_contains($html, 'cid:'.ShopBranding::LOGO_CID)) {
            return;
        }

        $file = public_path('images/urbanblade-mail-logo.png');
        if (! is_file($file)) {
            return;
        }

        $part = (new DataPart(new File($file), 'urbanblade.png', 'image/png'))->asInline();
        $part->setContentId(ShopBranding::LOGO_CID);
        $message->addPart($part);
    }
}
