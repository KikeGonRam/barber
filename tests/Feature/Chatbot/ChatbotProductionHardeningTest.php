<?php

namespace Tests\Feature\Chatbot;

use App\Models\User;
use App\Services\ChatbotExternalDataService;
use App\Services\ChatbotIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatbotProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'chatbot.rate_limit.max_attempts' => 1,
            'chatbot.rate_limit.decay_seconds' => 60,
            'services.gemini.api_key' => null,
        ]);
    }

    public function test_chatbot_rate_limit_returns_429_and_logs_event(): void
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        $this->app->instance(ChatbotIntelligenceService::class, new class extends ChatbotIntelligenceService {
            public function getContextualResponse(string $message, $user = null): ?string
            {
                return null;
            }
        });

        $this->app->instance(ChatbotExternalDataService::class, new class extends ChatbotExternalDataService {
            public function getExternalResponse(string $message): ?string
            {
                return null;
            }
        });

        $this->actingAs($user)
            ->postJson(route('chatbot.query'), ['message' => 'consulta uno'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('chatbot.query'), ['message' => 'consulta dos'])
            ->assertStatus(429)
            ->assertJsonStructure(['response', 'retry_after']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'chatbot',
            'description' => 'chatbot_rate_limited',
        ]);

        RateLimiter::clear("chatbot:user:{$user->id}");
    }

    public function test_chatbot_intelligence_exception_falls_back_and_logs_event(): void
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        $this->app->instance(ChatbotIntelligenceService::class, new class extends ChatbotIntelligenceService {
            public function getContextualResponse(string $message, $user = null): ?string
            {
                throw new \RuntimeException('Intelligence engine exploded');
            }
        });

        $this->app->instance(ChatbotExternalDataService::class, new class extends ChatbotExternalDataService {
            public function getExternalResponse(string $message): ?string
            {
                return null;
            }
        });

        $this->actingAs($user)
            ->postJson(route('chatbot.query'), ['message' => 'zzzzqwerty123'])
            ->assertOk()
            ->assertJsonStructure(['response']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'chatbot',
            'description' => 'chatbot_intelligence_error',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'chatbot',
            'description' => 'chatbot_fallback',
        ]);

        RateLimiter::clear("chatbot:user:{$user->id}");
    }

    private function createVerifiedUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
