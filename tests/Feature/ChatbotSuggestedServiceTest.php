<?php

namespace Tests\Feature;

use App\Models\BarbershopSetting;
use App\Models\ChatMessage;
use App\Models\Service;
use App\Services\Chatbot\ChatbotContextService;
use App\Services\Chatbot\Concerns\BuildsBarberSystemPrompt;
use App\Services\Chatbot\Contracts\ChatbotAiProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cuando la IA recomienda un servicio del catálogo, la respuesta trae suggested_service para que
 * la app muestre «Reservar» directo.
 */
class ChatbotSuggestedServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake();
    }

    protected function tearDown(): void
    {
        Service::query()->delete();
        ChatMessage::query()->delete();
        Cache::flush();

        parent::tearDown();
    }

    private function aiSays(string $text): void
    {
        $this->mock(ChatbotAiProvider::class, function ($mock) use ($text) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('buildSystemPrompt')->andReturn('Eres Bladebot.');
            $mock->shouldReceive('generateResponseWithPrompt')->andReturn($text);
            $mock->shouldReceive('label')->andReturn('ollama:test');
        });
    }

    public function test_ai_answer_that_names_a_service_carries_it_for_booking(): void
    {
        Service::create(['nombre' => 'Corte', 'precio' => 120, 'duracion_min' => 20, 'activo' => true]);
        $clasico = Service::create(['nombre' => 'Corte Clásico', 'precio' => 180, 'duracion_min' => 30, 'activo' => true]);
        $this->aiSays('Para cabello rizado te va muy bien un corte clasico con las puntas definidas.');

        $response = $this->postJson('/api/v1/chatbot/query', ['message' => 'mi cabello es rizado y quiero verme ordenado']);

        $response->assertOk()
            ->assertJsonPath('suggested_service.id', (string) $clasico->id)
            ->assertJsonPath('suggested_service.nombre', 'Corte Clásico');
    }

    public function test_ai_answer_without_a_catalog_service_has_no_suggestion(): void
    {
        Service::create(['nombre' => 'Corte Clásico', 'precio' => 180, 'duracion_min' => 30, 'activo' => true]);
        $this->aiSays('Toma mucha agua y descansa bien antes de tu cita.');

        $this->postJson('/api/v1/chatbot/query', ['message' => 'mi cabello es rizado y quiero verme ordenado'])
            ->assertOk()
            ->assertJsonPath('suggested_service', null);
    }

    public function test_ai_answer_is_shown_without_markdown_and_without_a_cut_sentence(): void
    {
        Service::create(['nombre' => 'Corte Clásico', 'precio' => 180, 'duracion_min' => 30, 'activo' => true]);
        // Lo que se vio en el cel el 26-sep: asteriscos a la vista y la frase cortada por el límite de tokens.
        $this->aiSays("Te recomiendo el **Corte Clásico** por \$180. Deja la barba con forma.\n\n3. **Skin Fade**: Un corte que te hace look más atractivo y te sienta bien en tu");

        $this->postJson('/api/v1/chatbot/query', ['message' => 'mi cabello es rizado y quiero verme ordenado'])
            ->assertOk()
            ->assertJsonPath('response', 'Te recomiendo el Corte Clásico por $180. Deja la barba con forma.');
    }

    public function test_a_repeated_question_answered_from_memory_is_tidied_and_keeps_the_booking_suggestion(): void
    {
        Service::create(['nombre' => 'Corte Clásico', 'precio' => 180, 'duracion_min' => 30, 'activo' => true]);
        $this->aiSays('No debería llamarse a la IA.');
        // Respuesta guardada antes de que existiera la limpieza (lo que el cel repetía el 26-sep).
        app(ChatbotContextService::class)->addMessage(
            'que corte me recomiendas si tengo barba larga',
            "1. **Corte Clásico**: Un clásico que te queda bien.\n\n3. **Skin Fade**: Un corte que te sienta bien en tu",
            'bot'
        );

        $this->postJson('/api/v1/chatbot/query', ['message' => 'Que corte me recomiendas si tengo barba larga'])
            ->assertOk()
            ->assertJsonPath('response', '1. Corte Clásico: Un clásico que te queda bien.')
            ->assertJsonPath('suggested_service.nombre', 'Corte Clásico');
    }

    public function test_the_prompt_uses_the_real_business_data_and_never_offers_qr(): void
    {
        BarbershopSetting::query()->delete();
        BarbershopSetting::create([
            'nombre' => 'UrbanBlade Centro', 'direccion' => 'Av. Juárez 120, Toluca', 'telefono' => '722 000 0000',
            'horario_apertura' => '09:00', 'horario_cierre' => '20:00', 'politica_cancelacion' => 12,
        ]);
        Cache::flush();
        Service::create(['nombre' => 'Corte Clásico', 'precio' => 180, 'duracion_min' => 30, 'activo' => true]);

        $prompt = null;
        $this->mock(ChatbotAiProvider::class, function ($mock) use (&$prompt) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('buildSystemPrompt')->andReturnUsing(function (array $data) use (&$prompt) {
                $prompt = (new class
                {
                    use BuildsBarberSystemPrompt;
                })->buildSystemPrompt($data);

                return $prompt;
            });
            $mock->shouldReceive('generateResponseWithPrompt')->andReturn('Te recomiendo el Corte Clásico.');
            $mock->shouldReceive('label')->andReturn('ollama:test');
        });

        $this->postJson('/api/v1/chatbot/query', ['message' => 'mi cabello es rizado y quiero verme ordenado'])->assertOk();

        $this->assertStringContainsString('Av. Juárez 120, Toluca', $prompt);
        $this->assertStringContainsString('de 09:00 a 20:00', $prompt);
        $this->assertStringContainsString('Corte Clásico ($180)', $prompt);
        $this->assertStringContainsString('hasta 12 horas antes', $prompt);
        $this->assertStringNotContainsString('Reforma', $prompt);
        $this->assertStringNotContainsString('QR', $prompt);

        BarbershopSetting::query()->delete();
    }
}
