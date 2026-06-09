<?php

namespace Tests\Feature\Observability;

use App\Http\Controllers\ChatbotController;
use App\Models\User;
use App\Services\BusinessEventService;
use App\Services\ChatbotContextService;
use App\Services\ChatbotExternalDataService;
use App\Services\ChatbotIntelligenceService;
use App\Services\ChatbotLearningService;
use App\Services\ChatbotUserProfileService;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BusinessEventLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_fallback_is_logged_when_response_is_manual(): void
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        config(['services.gemini.api_key' => null]);

        $chatbot = new ChatbotController(
            app(GeminiService::class),
            new class extends ChatbotIntelligenceService
            {
                public function getContextualResponse(string $message, $user = null): ?string
                {
                    return null;
                }
            },
            new class extends ChatbotExternalDataService
            {
                public function getExternalResponse(string $message): ?string
                {
                    return null;
                }
            },
            app(ChatbotContextService::class),
            app(ChatbotUserProfileService::class),
            app(ChatbotLearningService::class),
            app(BusinessEventService::class),
        );

        $request = Request::create('/chatbot/query', 'POST', [
            'message' => 'zzzzqwerty123',
        ]);

        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);
        $this->actingAs($user);

        $response = $chatbot->query($request);

        $this->assertSame(200, $response->status());
        $this->assertArrayHasKey('response', $response->getData(true));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'chatbot',
            'description' => 'chatbot_fallback',
        ]);
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
