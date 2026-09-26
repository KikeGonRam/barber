<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Service;
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
}
