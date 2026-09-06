<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chatbot\ChatbotContextService;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cubre la persistencia en Mongo (ChatMessage) agregada a
 * ChatbotContextService (fase de chat de asistencia IA en Nuxt): antes,
 * addMessage() solo escribía a sesión, lo que confirmado en vivo el
 * 2026-09-06 dejaba el historial completamente vacío para un cliente
 * Bearer-token sin cookie (Nuxt) -- dos llamadas seguidas al mismo endpoint
 * con el mismo token, sin cookie, nunca compartían sesión.
 */
class ChatbotContextServiceTest extends TestCase
{
    private ChatbotContextService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ChatbotContextService::class);
    }

    protected function tearDown(): void
    {
        ChatMessage::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create(['name' => 'Cliente Chat', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
    }

    public function test_add_message_persists_to_mongo_when_user_id_given(): void
    {
        $user = $this->makeUser();

        $this->service->addMessage('cual es el horario', 'Lunes a sabado', 'bot', (string) $user->id);

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => (string) $user->id,
            'message' => 'cual es el horario',
            'response' => 'Lunes a sabado',
        ]);
    }

    public function test_add_message_does_not_persist_for_guests(): void
    {
        $this->service->addMessage('hola', 'hola, como puedo ayudarte', 'bot', null);

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_get_persisted_history_returns_messages_oldest_first_with_context(): void
    {
        $user = $this->makeUser();

        $this->service->addMessage('primer mensaje', 'primera respuesta', 'bot', (string) $user->id);
        $this->service->addMessage('segundo mensaje sobre precio', 'segunda respuesta', 'bot', (string) $user->id);

        $history = $this->service->getPersistedHistory((string) $user->id);

        $this->assertCount(2, $history);
        $this->assertSame('primer mensaje', $history[0]['message']);
        $this->assertSame('segundo mensaje sobre precio', $history[1]['message']);
        $this->assertContains('precio', $history[1]['context']['keywords']);
    }

    public function test_get_persisted_history_is_isolated_per_user(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        $this->service->addMessage('mensaje de A', 'respuesta A', 'bot', (string) $userA->id);
        $this->service->addMessage('mensaje de B', 'respuesta B', 'bot', (string) $userB->id);

        $historyA = $this->service->getPersistedHistory((string) $userA->id);

        $this->assertCount(1, $historyA);
        $this->assertSame('mensaje de A', $historyA[0]['message']);
    }

    public function test_get_persisted_summary_counts_messages(): void
    {
        $user = $this->makeUser();

        $this->service->addMessage('uno', 'r1', 'bot', (string) $user->id);
        $this->service->addMessage('dos', 'r2', 'bot', (string) $user->id);
        $this->service->addMessage('tres', 'r3', 'bot', (string) $user->id);

        $summary = $this->service->getPersistedSummary((string) $user->id);

        $this->assertSame(3, $summary['total_messages']);
    }

    public function test_clear_history_removes_persisted_messages(): void
    {
        $user = $this->makeUser();

        $this->service->addMessage('uno', 'r1', 'bot', (string) $user->id);
        $this->assertSame(1, ChatMessage::where('user_id', (string) $user->id)->count());

        $this->service->clearHistory((string) $user->id);

        $this->assertSame(0, ChatMessage::where('user_id', (string) $user->id)->count());
    }
}
