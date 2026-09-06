<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\Chatbot\ChatbotIntelligenceService;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresión: gatherExtendedContext() -> getUserPreferences() calculaba el
 * gasto promedio con `Payment::whereHas(...)->recent()->average('monto')`,
 * pero `recent()` nunca existió como scope de `Payment` -- lanzaba una
 * excepción en CADA mensaje de un cliente autenticado con perfil de
 * cliente. El try/catch de ChatbotController::query() alrededor de
 * getContextualResponse() la silenciaba (nunca se veía como error real),
 * pero el efecto real era que la "Respuesta Inteligente" (capa local,
 * rápida, sin IA) siempre fallaba y caía a lógica manual/externa/IA para
 * cualquier cliente con perfil -- toda una capa del chatbot rota en
 * silencio. Encontrado en Larastan: estaba en el baseline como "Call to an
 * undefined method Builder<Payment>::recent()", suprimido en vez de
 * arreglado.
 *
 * Segundo bug (mismo archivo, encontrado al arreglar el primero y volver a
 * correr el test): 'monto' está cast como 'decimal:2', así que
 * Payment::average()/sum() devuelven un MongoDB\BSON\Decimal128, no un
 * int|float -- round() nunca lo aceptó. Rompía average_spending Y
 * getBusinessStats() (today_revenue/month_revenue, esta última nunca antes
 * ejercitada porque la primera ya tronaba antes de llegar ahí). Arreglado
 * con un helper toFloat() compartido.
 */
class ChatbotIntelligenceServiceTest extends TestCase
{
    private ChatbotIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ChatbotIntelligenceService::class);
    }

    protected function tearDown(): void
    {
        Payment::query()->delete();
        Appointment::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    public function test_gather_extended_context_does_not_throw_for_a_client_with_payments(): void
    {
        $user = User::create(['name' => 'Cliente Test', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $user->id, 'telefono' => '5551234567']);
        $barberUser = User::create(['name' => 'Barbero Test', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Test', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte clásico', 'precio' => 300, 'duracion_min' => 30, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->subDays(5)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
        ]);

        Payment::create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 300,
            'metodo_pago' => 'efectivo',
            'estado' => Payment::ESTADO_VERIFICADO,
        ]);

        $context = $this->service->gatherExtendedContext($user);

        $this->assertSame(300.0, $context['user_preferences']['average_spending']);
        $this->assertSame(300.0, $context['stats']['today_revenue']);
        $this->assertSame(300.0, $context['stats']['month_revenue']);
    }

    public function test_gather_extended_context_does_not_throw_for_a_client_with_no_payments(): void
    {
        $user = User::create(['name' => 'Cliente Sin Pagos', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        Client::create(['user_id' => (string) $user->id, 'telefono' => '5559876543']);

        $context = $this->service->gatherExtendedContext($user);

        $this->assertSame(0.0, $context['user_preferences']['average_spending']);
    }
}
