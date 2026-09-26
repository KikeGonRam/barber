<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\BriefingService;
use App\Services\Ai\LocalAi;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La IA local nunca frena un flujo: límite de tiempo, cortacircuito y respuesta sin IA al instante.
 */
class LocalAiBriefingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'chatbot.ai.provider' => 'ollama',
            'chatbot.ai.ollama.url' => 'http://ollama.test:11434',
            'chatbot.ai.ollama.model' => 'qwen2.5:0.5b',
        ]);
        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::flush();

        parent::tearDown();
    }

    private function tokenFor(string $roleName, string $email): string
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName), 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $plain = 'token-ia-'.$roleName.'-'.uniqid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain)]);

        return $plain;
    }

    public function test_complete_returns_the_model_text(): void
    {
        Http::fake(['ollama.test:11434/api/generate' => Http::response(['response' => ' Hola, bienvenido. '])]);

        $this->assertSame('Hola, bienvenido.', app(LocalAi::class)->complete('Di hola'));
    }

    public function test_a_failure_opens_the_breaker_and_nobody_waits_afterwards(): void
    {
        Http::fake(['ollama.test:11434/api/generate' => Http::response('error', 500)]);
        $ai = app(LocalAi::class);

        $this->assertNull($ai->complete('Di hola'));
        $this->assertFalse($ai->available());

        // Con el cortacircuito abierto ni siquiera se llama a Ollama.
        $this->assertNull($ai->complete('Otra vez'));
        Http::assertSentCount(1);
    }

    public function test_briefing_answers_at_once_by_rules_when_there_is_no_ai_text_yet(): void
    {
        Cache::put('local_ai:down', true, 120);

        $briefing = app(BriefingService::class)->briefing('recepcion', [
            'citas_hoy' => 8, 'citas_por_cobrar' => 2, 'pedidos_por_entregar' => 1, 'productos_con_stock_bajo' => 0,
        ]);

        $this->assertSame('reglas', $briefing['source']);
        $this->assertSame('Hoy hay 8 citas en la agenda. Conviene atender primero: 2 por cobrar, 1 pedido por entregar.', $briefing['text']);
    }

    public function test_briefing_returns_the_saved_ai_text_when_it_is_fresh(): void
    {
        Http::fake();
        $facts = ['citas_hoy' => 3];
        Cache::put('ai_briefing:recepcion:'.now()->toDateString(), [
            'text' => 'Día tranquilo; revisa los cobros pendientes.',
            'generated_at' => now()->toIso8601String(),
            'facts_hash' => md5((string) json_encode($facts)),
        ], 3600);

        $briefing = app(BriefingService::class)->briefing('recepcion', $facts);

        $this->assertSame('ia', $briefing['source']);
        $this->assertSame('Día tranquilo; revisa los cobros pendientes.', $briefing['text']);
        Http::assertNothingSent();
    }

    public function test_endpoint_is_for_reception_and_admin_only(): void
    {
        Cache::put('local_ai:down', true, 120);

        $this->withToken($this->tokenFor('recepcionista', 'recepcion-ia@test.local'))
            ->getJson('/api/v1/ai/briefing')
            ->assertOk()
            ->assertJsonPath('data.source', 'reglas');

        $this->withToken($this->tokenFor('cliente', 'cliente-ia@test.local'))
            ->getJson('/api/v1/ai/briefing')
            ->assertForbidden();
    }
}
