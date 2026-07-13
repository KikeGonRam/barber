<?php

namespace App\Notifications\Channels;

use App\Services\Messaging\MessagingService;
use Illuminate\Notifications\Notification;

/**
 * Canal de notificacion por SMS/WhatsApp usando MessagingService (Twilio).
 * Si Twilio no esta configurado, MessagingService simula el envio (log) sin
 * romper nada. Prefiere WhatsApp si el usuario lo tiene activo, si no SMS.
 */
class TwilioChannel
{
    public function __construct(private readonly MessagingService $messaging) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTwilio')) {
            return;
        }

        $to = method_exists($notifiable, 'routeNotificationForTwilio')
            ? $notifiable->routeNotificationForTwilio()
            : null;

        $message = (string) $notification->toTwilio($notifiable);

        if (! $to || $message === '') {
            return;
        }

        $wantsWhatsapp = method_exists($notifiable, 'wantsNotificationChannel')
            && $notifiable->wantsNotificationChannel('whatsapp');

        if ($wantsWhatsapp) {
            $this->messaging->sendWhatsapp($to, $message);
        } else {
            $this->messaging->sendSms($to, $message);
        }
    }
}
