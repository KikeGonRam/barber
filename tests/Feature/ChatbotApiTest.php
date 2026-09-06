<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cubre la API de chat de asistencia IA consumida por Nuxt (fase de chat en
 * el frontend): antes de esta fase, POST /api/v1/chatbot/query no tenía
 * NINGÚN middleware de auth (mismo landmine que social/feed antes de su
 * enriquecimiento) -- auth()->user() siempre era null para un cliente
 * Bearer-token, así que el mensaje nunca quedaba asociado a nadie y
 * GET /api/v1/chatbot/history (que lee de sesión) siempre devolvía vacío
 * para un cliente sin cookie. Confirmado en vivo antes de escribir este
 * test: dos POST seguidos con el mismo token, sin cookie, devolvían
 * historial vacío.
 */
class ChatbotApiTest extends TestCase
{
    private string $token = 'test-chatbot-token';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $this->user = User::create(['name' => 'Cliente Chatbot', 'email' => 'cliente-chatbot@test.local', 'password' => 'password']);
        $this->user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $this->user->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->token)]);
    }

    protected function tearDown(): void
    {
        ChatMessage::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_query_with_a_valid_token_persists_the_message_for_that_user(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/chatbot/query', ['message' => 'cual es el horario'])
            ->assertOk();

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => (string) $this->user->id,
            'message' => 'cual es el horario',
        ]);
    }

    public function test_query_without_a_token_does_not_persist_anything(): void
    {
        // Mismo mensaje que el resto de este archivo (no "hola" u otro
        // saludo genérico): esos no calzan con ninguna palabra clave de
        // manualLogic() y la cascada cae hasta el proveedor de IA real,
        // haciendo el test lento y dependiente de red/Ollama/Gemini.
        // "cual es el horario" siempre resuelve por manualLogic(), sin red.
        $this->postJson('/api/v1/chatbot/query', ['message' => 'cual es el horario'])->assertOk();

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_history_and_clear_history_use_the_persisted_mongo_copy(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/chatbot/query', ['message' => 'cual es el horario'])
            ->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/v1/chatbot/history')
            ->assertOk()
            ->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.message', 'cual es el horario')
            ->assertJsonPath('summary.total_messages', 1);

        $this->withToken($this->token)
            ->postJson('/api/v1/chatbot/clear-history')
            ->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/v1/chatbot/history')
            ->assertOk()
            ->assertJsonCount(0, 'history');
    }

    /**
     * Regresión: getProfile() leía el resumen desde ChatbotContextService::
     * getConversationSummary() (sesión), el mismo problema que ya tenía
     * getHistory() antes de la Fase 3 -- un cliente Bearer-token sin
     * cookie nunca vería su propio resumen reflejado ahí.
     */
    public function test_profile_summary_reflects_persisted_history(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/chatbot/query', ['message' => 'cual es el horario'])
            ->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/v1/chatbot/profile')
            ->assertOk()
            ->assertJsonPath('summary.total_messages', 1);
    }

    /**
     * Regresión: getLearningStats()/trainFromHistory() llamaban a métodos
     * que no existen en ChatbotContextService (getUserLearningStats()/
     * trainFromHistory()) -- 500 fatal garantizado si alguna vez se
     * invocaban. Nunca se detectó porque nada los ejercitaba: cero tests
     * antes de esta fase.
     */
    public function test_learning_stats_returns_ok(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/chatbot/learning-stats')
            ->assertOk()
            ->assertJsonStructure(['stats', 'top_categories']);
    }

    public function test_train_from_history_is_admin_only_and_works_for_admins(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/chatbot/train-history')
            ->assertForbidden();

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Chatbot', 'email' => 'admin-chatbot@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $adminToken = 'test-chatbot-admin-token';
        MobileApiToken::create(['user_id' => (string) $admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $adminToken)]);

        $this->withToken($adminToken)
            ->postJson('/api/v1/chatbot/train-history')
            ->assertOk()
            ->assertJsonStructure(['message', 'result']);
    }

    /**
     * Nuevo endpoint (inspirado en cómo otros widgets de chat de
     * asistencia -- p. ej. el "Fin" de Intercom -- dejan calificar cada
     * respuesta): antes, recordFeedback() solo se llamaba con
     * $wasHelpful hardcodeado en `true` desde las 5 ramas de
     * ChatbotController::query() -- el sistema de aprendizaje nunca había
     * recibido una señal negativa real.
     */
    public function test_feedback_requires_message_response_and_helpful(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/chatbot/feedback', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message', 'response', 'helpful']);
    }

    public function test_feedback_is_accepted_for_an_authenticated_user(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/chatbot/feedback', [
                'message' => 'cual es el horario',
                'response' => 'Lunes a sábado 9-9',
                'helpful' => false,
            ])
            ->assertOk()
            ->assertJsonStructure(['message']);
    }

    /**
     * Regresión: matchesKeywords() comparaba texto sin quitar acentos, así
     * que un mensaje escrito sin ellos ("cuanto cuesta", como escribe casi
     * cualquiera al chatear rápido) nunca calzaba con la keyword literal
     * 'cuánto cuesta' de la categoría de precios/servicios y terminaba
     * cayendo hasta datos externos/IA -- confirmado en vivo que
     * "cuanto cuesta un fade" devolvía contenido de Wikipedia sobre
     * ingeniería de audio, no información de la barbería.
     */
    public function test_pricing_question_without_accents_matches_the_local_pricing_answer(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/chatbot/query', ['message' => 'cuanto cuesta un fade'])
            ->assertOk()
            ->assertJsonPath('response', 'Contamos con una amplia gama de servicios de barbería premium incluyendo cortes, afeitados, tratamientos capilares y más. Visita la sección Servicios para ver detalles y precios.');
    }
}
