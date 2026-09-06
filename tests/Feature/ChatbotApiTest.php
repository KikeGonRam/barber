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
}
