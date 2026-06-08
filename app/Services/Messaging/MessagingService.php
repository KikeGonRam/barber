<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessagingService
{
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
