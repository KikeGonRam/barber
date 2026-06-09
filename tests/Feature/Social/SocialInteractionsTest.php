<?php

namespace Tests\Feature\Social;

use App\Models\Barber;
use App\Models\User;
use App\Models\Work;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

/**
 * FASE 4 — Interacciones sociales
 *
 * Cubre el SocialController y ApiSocialController:
 * - Reacciones (like/unlike)
 * - Guardados (save/unsave)
 * - Comentarios
 * - Feed social público y autenticado
 * - Portafolio de barbero (web)
 */
class SocialInteractionsTest extends TestCase
{
    use RefreshMongoDatabase;

    private function createWorkForBarber(): array
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->assignRole('barbero');

        $barber = Barber::factory()->create(['user_id' => $user->id, 'activo' => true]);

        $work = Work::create([
            'barbero_id'  => (string) $user->id,
            'title'       => 'Corte de prueba',
            'description' => 'Trabajo de ejemplo',
            'work_date'   => now()->toDateString(),
        ]);

        return [$user, $barber, $work];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // FEED SOCIAL
    // ──────────────────────────────────────────────────────────────────────────

    public function test_social_feed_is_publicly_accessible(): void
    {
        $this->get('/descubrir')->assertOk();
    }

    public function test_api_social_feed_is_publicly_accessible(): void
    {
        $this->getJson('/api/v1/social/feed')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // REACCIONES — web
    // ──────────────────────────────────────────────────────────────────────────

    public function test_react_requires_authentication(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $this->post('/social/work/'.$work->id.'/react')
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_react_to_work(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('cliente');

        $response = $this->actingAs($user)
            ->postJson('/social/work/'.$work->id.'/react');

        $response->assertOk()
                 ->assertJsonStructure(['status', 'count']);

        $this->assertEquals('added', $response->json('status'));
        $this->assertEquals(1, $response->json('count'));
    }

    public function test_react_twice_removes_reaction(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('cliente');

        $this->actingAs($user)->postJson('/social/work/'.$work->id.'/react');
        $response = $this->actingAs($user)->postJson('/social/work/'.$work->id.'/react');

        $response->assertOk();
        $this->assertEquals('removed', $response->json('status'));
        $this->assertEquals(0, $response->json('count'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GUARDADOS — web
    // ──────────────────────────────────────────────────────────────────────────

    public function test_save_requires_authentication(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $this->post('/social/work/'.$work->id.'/save')
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_save_work(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('cliente');

        $response = $this->actingAs($user)
            ->postJson('/social/work/'.$work->id.'/save');

        $response->assertOk()
                 ->assertJsonStructure(['status']);
        $this->assertEquals('added', $response->json('status'));
    }

    public function test_save_twice_removes_saved_work(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('cliente');

        $this->actingAs($user)->postJson('/social/work/'.$work->id.'/save');
        $response = $this->actingAs($user)->postJson('/social/work/'.$work->id.'/save');

        $response->assertOk();
        $this->assertEquals('removed', $response->json('status'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // COMENTARIOS — web
    // ──────────────────────────────────────────────────────────────────────────

    public function test_comment_requires_authentication(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $this->post('/social/work/'.$work->id.'/comment', ['comment' => 'Hola'])
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_comment_on_work(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('cliente');

        $this->actingAs($user)
            ->postJson('/social/work/'.$work->id.'/comment', [
                'comment' => 'Excelente trabajo!',
            ])
            ->assertOk()
            ->assertJsonStructure(['comment']);
    }

    public function test_comment_validates_content_is_required(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('cliente');

        $this->actingAs($user)
            ->postJson('/social/work/'.$work->id.'/comment', ['comment' => ''])
            ->assertUnprocessable();
    }

    public function test_comment_validates_max_500_chars(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('cliente');

        $this->actingAs($user)
            ->postJson('/social/work/'.$work->id.'/comment', [
                'comment' => str_repeat('a', 501),
            ])
            ->assertUnprocessable();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // INTERACCIONES VÍA API (Social API)
    // ──────────────────────────────────────────────────────────────────────────

    private function apiClientToken(): string
    {
        $user = User::factory()->create([
            'email'             => 'api-social@test.com',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('cliente');

        return $this->postJson('/api/v1/auth/login', [
            'email'       => 'api-social@test.com',
            'password'    => 'password',
            'device_name' => 'test-device',
        ])->json('token');
    }

    public function test_api_react_requires_auth(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $this->postJson('/api/v1/social/work/'.$work->id.'/react')
            ->assertUnauthorized();
    }

    public function test_api_react_authenticated_returns_200(): void
    {
        [, , $work] = $this->createWorkForBarber();
        $token = $this->apiClientToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/social/work/'.$work->id.'/react')
            ->assertOk()
            ->assertJsonStructure(['status', 'count']);
    }

    public function test_api_save_requires_auth(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $this->postJson('/api/v1/social/work/'.$work->id.'/save')
            ->assertUnauthorized();
    }

    public function test_api_comment_requires_auth(): void
    {
        [, , $work] = $this->createWorkForBarber();

        $this->postJson('/api/v1/social/work/'.$work->id.'/comment', ['comment' => 'test'])
            ->assertUnauthorized();
    }
}
