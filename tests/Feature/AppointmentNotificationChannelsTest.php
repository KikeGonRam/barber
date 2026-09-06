<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Notifications\Appointment\AppointmentNotification;
use App\Notifications\Channels\TwilioChannel;
use App\Notifications\Channels\WebPushChannel;
use Tests\TestCase;

/**
 * Cubre el canal 'push' agregado a AppointmentNotification::via() (fase de
 * notificaciones push para citas próximas) — sin esto, un usuario con
 * push habilitado nunca hubiera recibido el WebPushChannel en su lista de
 * canales, aunque el resto de la infraestructura (VAPID, PushSubscription,
 * WebPushService) estuviera perfecta.
 */
class AppointmentNotificationChannelsTest extends TestCase
{
    private function makeNotification(): AppointmentNotification
    {
        return new AppointmentNotification(
            appointment: new Appointment,
            subject: 'Asunto',
            title: 'Título',
            message: 'Mensaje',
        );
    }

    public function test_via_includes_web_push_channel_when_enabled(): void
    {
        $notifiable = new class
        {
            public function wantsNotificationChannel(string $channel): bool
            {
                return $channel === 'push';
            }
        };

        $channels = $this->makeNotification()->via($notifiable);

        $this->assertContains(WebPushChannel::class, $channels);
        $this->assertNotContains('database', $channels);
        $this->assertNotContains('mail', $channels);
        $this->assertNotContains(TwilioChannel::class, $channels);
    }

    public function test_via_omits_web_push_channel_when_disabled(): void
    {
        $notifiable = new class
        {
            public function wantsNotificationChannel(string $channel): bool
            {
                return false;
            }
        };

        $channels = $this->makeNotification()->via($notifiable);

        $this->assertNotContains(WebPushChannel::class, $channels);
        // Sin ningun canal activo, cae a 'database' para no perder el aviso.
        $this->assertSame(['database'], $channels);
    }

    public function test_to_web_push_returns_title_body_and_url(): void
    {
        $payload = $this->makeNotification()->toWebPush(new class
        {
            public string $id = 'fake-id';
        });

        $this->assertSame('Título', $payload['title']);
        $this->assertSame('Mensaje', $payload['body']);
        $this->assertStringContainsString('/my/appointments', $payload['url']);
    }
}
