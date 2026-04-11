<?php

namespace Tests\Feature\Chatbot;

use App\Models\User;
use App\Services\ChatbotExternalDataService;
use App\Services\ChatbotIntelligenceService;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            'chatbot.telemetry.enabled' => true,
            'chatbot.telemetry.sample_rate' => 1,
            'chatbot.telemetry.ai_cost_per_1k_tokens' => 0.001,
        ]);
    }

    public function test_chatbot_rate_limit_returns_429_and_logs_event(): void
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        $this->app->instance(ChatbotIntelligenceService::class, new class extends ChatbotIntelligenceService
        {
            public function getContextualResponse(string $message, $user = null): ?string
            {
                return null;
            }
        });

        $this->app->instance(ChatbotExternalDataService::class, new class extends ChatbotExternalDataService
        {
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

        $this->assertTelemetryEventExists('rate_limit', 'blocked');

        RateLimiter::clear("chatbot:user:{$user->id}");
    }

    public function test_chatbot_intelligence_exception_falls_back_and_logs_event(): void
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        $this->app->instance(ChatbotIntelligenceService::class, new class extends ChatbotIntelligenceService
        {
            public function getContextualResponse(string $message, $user = null): ?string
            {
                throw new \RuntimeException('Intelligence engine exploded');
            }
        });

        $this->app->instance(ChatbotExternalDataService::class, new class extends ChatbotExternalDataService
        {
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

        $this->assertTelemetryEventExists('intelligence', 'error');
        $this->assertTelemetryEventExists('manual', 'fallback');

        RateLimiter::clear("chatbot:user:{$user->id}");
    }

    public function test_chatbot_gemini_success_logs_telemetry_with_cost_estimate(): void
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        config(['services.gemini.api_key' => 'fake-key']);

        $this->app->instance(ChatbotIntelligenceService::class, new class extends ChatbotIntelligenceService
        {
            public function getContextualResponse(string $message, $user = null): ?string
            {
                return null;
            }
        });

        $this->app->instance(ChatbotExternalDataService::class, new class extends ChatbotExternalDataService
        {
            public function getExternalResponse(string $message): ?string
            {
                return null;
            }
        });

        $this->app->instance(GeminiService::class, new class extends GeminiService
        {
            public function buildSystemPrompt(array $contextData): string
            {
                return 'system prompt for telemetry';
            }

            public function generateResponseWithPrompt(string $fullPrompt): string
            {
                return 'Respuesta AI de prueba';
            }
        });

        $this->actingAs($user)
            ->postJson(route('chatbot.query'), ['message' => 'zzzzqwerty123'])
            ->assertOk()
            ->assertJson(['response' => 'Respuesta AI de prueba']);

        $telemetry = $this->findTelemetryEvent('gemini', 'success');

        $this->assertNotNull($telemetry);
        $this->assertArrayHasKey('estimated_cost_usd', $telemetry);
        $this->assertGreaterThan(0, (float) $telemetry['estimated_cost_usd']);
        $this->assertGreaterThan(0, (int) ($telemetry['total_tokens_estimate'] ?? 0));

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

    private function assertTelemetryEventExists(string $source, string $status): void
    {
        $telemetry = $this->findTelemetryEvent($source, $status);

        $this->assertNotNull($telemetry);
        $this->assertArrayHasKey('latency_ms', $telemetry);
        $this->assertGreaterThanOrEqual(0, (int) $telemetry['latency_ms']);
    }

    private function findTelemetryEvent(string $source, string $status): ?array
    {
        $rows = DB::table('activity_log')
            ->where('log_name', 'chatbot')
            ->where('description', 'chatbot_provider_telemetry')
            ->orderByDesc('id')
            ->get();

        foreach ($rows as $row) {
            $properties = json_decode((string) $row->properties, true);

            if (($properties['source'] ?? null) === $source && ($properties['status'] ?? null) === $status) {
                return $properties;
            }
        }

        return null;
    }
}
