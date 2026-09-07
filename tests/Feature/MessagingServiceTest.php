<?php

namespace Tests\Feature;

use App\Services\Messaging\MessagingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Fase 5 (notificaciones y operación): antes, una respuesta de error de
 * Twilio (número inválido, credenciales rechazadas, rate limit, etc.) era
 * completamente invisible -- Http::post() sin ->throw()/->successful() no
 * lanza excepción ni deja rastro. Cubre que ahora sí se registra un
 * Log::warning con el status/body de la respuesta fallida.
 */
class MessagingServiceTest extends TestCase
{
    public function test_send_sms_logs_a_warning_when_twilio_responds_with_an_error(): void
    {
        config([
            'services.twilio.sid' => 'AC-test-sid',
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15550001111',
        ]);

        Http::fake([
            'api.twilio.com/*' => Http::response(['message' => 'The number is not a valid phone number'], 400),
        ]);

        Log::spy();

        (new MessagingService)->sendSms('+15559998888', 'Recordatorio de cita');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'Fallo envio de SMS via Twilio'
                && $context['to'] === '+15559998888'
                && $context['status'] === 400
            );
    }

    public function test_send_sms_does_not_log_when_twilio_succeeds(): void
    {
        config([
            'services.twilio.sid' => 'AC-test-sid',
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15550001111',
        ]);

        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        Log::spy();

        (new MessagingService)->sendSms('+15559998888', 'Recordatorio de cita');

        Log::shouldNotHaveReceived('warning');
    }

    public function test_send_whatsapp_logs_a_warning_when_twilio_responds_with_an_error(): void
    {
        config([
            'services.twilio.sid' => 'AC-test-sid',
            'services.twilio.token' => 'test-token',
            'services.twilio.whatsapp_from' => '+15550001111',
        ]);

        Http::fake([
            'api.twilio.com/*' => Http::response(['message' => 'Unable to create record'], 500),
        ]);

        Log::spy();

        (new MessagingService)->sendWhatsapp('+15559998888', 'Recordatorio de cita');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'Fallo envio de WhatsApp via Twilio'
                && $context['to'] === '+15559998888'
                && $context['status'] === 500
            );
    }

    public function test_send_sms_simulates_without_hitting_the_network_when_twilio_is_not_configured(): void
    {
        config(['services.twilio.sid' => null, 'services.twilio.token' => null, 'services.twilio.from' => null]);

        Http::fake();

        (new MessagingService)->sendSms('+15559998888', 'Recordatorio de cita');

        Http::assertNothingSent();
    }
}
