<?php

namespace Tests\Feature\Observability;

use App\Models\Inventory;
use App\Models\User;
use App\Http\Controllers\ChatbotController;
use App\Services\ChatbotExternalDataService;
use App\Services\ChatbotIntelligenceService;
use App\Services\ChatbotContextService;
use App\Services\ChatbotLearningService;
use App\Services\ChatbotUserProfileService;
use App\Services\BusinessEventService;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BusinessEventLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_index_logs_low_stock_alert(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        Inventory::query()->create([
            'name' => 'Cera Premium',
            'quantity' => 1,
            'min_stock' => 2,
            'price' => 99.99,
            'description' => 'Producto de prueba',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('almacen.index'))
            ->assertOk()
            ->assertSee('Alerta de Stock Crítico');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'alerts',
            'description' => 'low_stock_detected',
        ]);
    }

    public function test_inventory_updates_are_recorded_in_activity_log(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $inventory = Inventory::query()->create([
            'name' => 'Shampoo Premium',
            'quantity' => 5,
            'min_stock' => 2,
            'price' => 120,
            'description' => 'Producto inicial',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('almacen.update', $inventory), [
                'name' => 'Shampoo Premium Plus',
                'quantity' => 8,
                'min_stock' => 3,
                'price' => 140,
                'description' => 'Actualizado',
                'active' => true,
            ])
            ->assertRedirect(route('almacen.index'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'inventory_items',
            'subject_type' => Inventory::class,
            'subject_id' => $inventory->id,
            'description' => 'updated',
        ]);
    }

    public function test_chatbot_fallback_is_logged_when_response_is_manual(): void
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        config(['services.gemini.api_key' => null]);

        $chatbot = new ChatbotController(
            app(GeminiService::class),
            new class extends ChatbotIntelligenceService {
                public function getContextualResponse(string $message, $user = null): ?string
                {
                    return null;
                }
            },
            new class extends ChatbotExternalDataService {
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
