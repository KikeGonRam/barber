<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chatbot\ChatbotContextService;
use Illuminate\Support\Facades\Session;
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

    /**
     * Regresión (encontrada en vivo, 2026-09-06, reportada por el usuario
     * como "pierde el contexto"): getConversationHistory() solo leía de
     * sesión. addMessage() ya persistía a Mongo desde la Fase 3, pero
     * findSimilarQuestions()/isFollowUp()/getAugmentedContext() -- todo el
     * motor de "recuerda la conversación" -- seguían leyendo únicamente de
     * sesión. Un cliente Bearer-token (Nuxt) nunca comparte sesión entre
     * requests, así que cada mensaje llegaba al motor sin memoria de nada
     * anterior, aunque el widget sí mostrara el historial completo al
     * reabrirse (eso leía de Mongo desde la Fase 3, el motor no).
     */
    public function test_get_conversation_history_falls_back_to_persisted_when_session_is_empty(): void
    {
        $user = $this->makeUser();
        $this->service->addMessage('cual es el horario', 'Lunes a sabado 9-9', 'bot', (string) $user->id);

        // Simula una request nueva sin cookie de sesión (como Nuxt).
        Session::flush();

        $history = $this->service->getConversationHistory((string) $user->id);

        $this->assertCount(1, $history);
        $this->assertSame('cual es el horario', $history[0]['message']);
    }

    /**
     * Segundo bug encontrado en la misma investigación: el filtro
     * `type === 'user'` de findSimilarQuestions() nunca coincidía con nada
     * -- addMessage() siempre guarda type='bot' en TODOS sus call sites
     * (cada entrada ya es un turno completo pregunta+respuesta, no hay
     * entradas separadas por rol). La rama de "memoria instantánea" (la
     * más rápida de toda la cascada de ChatbotController::query(), antes
     * incluso de lógica manual/IA) nunca se había activado, ni para el
     * widget Blade ni para nadie.
     */
    public function test_find_similar_questions_matches_a_repeated_question(): void
    {
        $user = $this->makeUser();
        $this->service->addMessage('cual es el horario', 'Lunes a sabado 9-9', 'bot', (string) $user->id);
        Session::flush();

        $similar = $this->service->findSimilarQuestions('cual es el horario', (string) $user->id, 70);

        $this->assertCount(1, $similar);
        $this->assertSame(100.0, $similar[0]['similarity']);
        $this->assertSame('Lunes a sabado 9-9', $similar[0]['answer']);
    }

    /**
     * Tercer bug en la misma investigación: formatHistoryForAI() (privado,
     * probado aquí a través de generateAugmentedPrompt()) usaba el mismo
     * `type === 'user'` roto para decidir la etiqueta de cada línea --
     * como 'type' siempre es 'bot', el historial que se le mandaba a la
     * IA como contexto etiquetaba SIEMPRE como "Bot" el mensaje del propio
     * CLIENTE, y nunca incluía la respuesta real del bot.
     */
    public function test_generate_augmented_prompt_attributes_client_and_bot_lines_correctly(): void
    {
        $user = $this->makeUser();
        $this->service->addMessage('cual es el horario', 'Lunes a sabado 9-9', 'bot', (string) $user->id);
        Session::flush();

        $prompt = $this->service->generateAugmentedPrompt('y el domingo', 'Eres el asistente de UrbanBlade.', (string) $user->id);

        $this->assertStringContainsString('Cliente: cual es el horario', $prompt);
        $this->assertStringContainsString('Bot: Lunes a sabado 9-9', $prompt);
    }

    public function test_get_persisted_summary_counts_both_user_and_bot_messages(): void
    {
        $user = $this->makeUser();
        $this->service->addMessage('uno', 'r1', 'bot', (string) $user->id);
        $this->service->addMessage('dos', 'r2', 'bot', (string) $user->id);

        $summary = $this->service->getPersistedSummary((string) $user->id);

        $this->assertSame(2, $summary['user_messages']);
        $this->assertSame(2, $summary['bot_messages']);
    }
}
