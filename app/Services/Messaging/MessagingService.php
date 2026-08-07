<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envia mensajes salientes (SMS y WhatsApp) via la API de Twilio.
 * Orquesta el canal de notificaciones externas al cliente/barbero para
 * flujos como confirmacion de citas o avisos de pago.
 */
class MessagingService
{
    /**
     * Envia un SMS via Twilio. Si Twilio no esta configurado (falta sid,
     * token o from), no lanza error: solo simula el envio con un log info,
     * util para entornos de desarrollo sin credenciales reales.
     */
    public function sendSms(string $to, string $message): void
    {
        if (! config('services.twilio.sid') || ! config('services.twilio.token') || ! config('services.twilio.from')) {
            Log::info('SMS simulado (Twilio no configurado)', ['to' => $to, 'message' => $message]);

            return;
        }

        Http::withBasicAuth(config('services.twilio.sid'), config('services.twilio.token'))
            ->asForm()
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.sid').'/Messages.json', [
                'From' => config('services.twilio.from'),
                'To' => $to,
                'Body' => $message,
            ]);
    }

    /**
     * Envia un mensaje de WhatsApp via Twilio (mismo endpoint que SMS pero
     * con prefijo "whatsapp:" en los numeros). Simula el envio con log si
     * falta configuracion, igual que sendSms().
     */
    public function sendWhatsapp(string $to, string $message): void
    {
        if (! config('services.twilio.sid') || ! config('services.twilio.token') || ! config('services.twilio.whatsapp_from')) {
            Log::info('WhatsApp simulado (Twilio no configurado)', ['to' => $to, 'message' => $message]);

            return;
        }

        Http::withBasicAuth(config('services.twilio.sid'), config('services.twilio.token'))
            ->asForm()
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.sid').'/Messages.json', [
                'From' => 'whatsapp:'.config('services.twilio.whatsapp_from'),
                'To' => 'whatsapp:'.$to,
                'Body' => $message,
            ]);
    }
}
