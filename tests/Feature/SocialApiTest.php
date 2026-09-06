<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Comment;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Reaction;
use App\Models\Role;
use App\Models\SavedWork;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkImage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * API del muro social (Fase Muro/Reseñas): cubre el enriquecimiento aditivo
 * de feed() (campo 'media' con tipo imagen/video, comentarios recientes,
 * slug/foto del barbero) — antes no tenía ningún test, y 'images' (array
 * plano de URLs) se conserva sin cambios por compatibilidad.
 */
class SocialApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Comment::query()->delete();
        Reaction::query()->delete();
        SavedWork::query()->delete();
        WorkImage::query()->delete();
        Work::query()->delete();
        Barber::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_feed_includes_media_type_and_recent_comments(): void
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $viewer = User::create(['name' => 'Viewer', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $viewer->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $token = 'test-social-feed-token';
        MobileApiToken::create(['user_id' => (string) $viewer->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $barberUser = User::create(['name' => 'Barbero Muro', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Muro', 'activo' => true]);

        $work = Work::create(['barbero_id' => (string) $barberUser->id, 'title' => 'Fade limpio', 'description' => 'Trabajo de prueba']);
        $work->images()->create(['image' => 'works/fade.jpg', 'type' => 'image']);
        $work->images()->create(['image' => 'works/fade.mp4', 'type' => 'video']);
        Comment::create(['work_id' => (string) $work->id, 'user_id' => (string) $viewer->id, 'comment' => 'Quedó increíble']);
        Reaction::create(['work_id' => (string) $work->id, 'user_id' => (string) $viewer->id, 'type' => 'like']);

        $response = $this->withToken($token)->getJson('/api/v1/social/feed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.images')
            ->assertJsonCount(2, 'data.0.media')
            ->assertJsonPath('data.0.media.0.type', 'image')
            ->assertJsonPath('data.0.media.1.type', 'video')
            ->assertJsonPath('data.0.barber.slug', $barber->slug)
            ->assertJsonPath('data.0.is_reacted', true)
            ->assertJsonPath('data.0.comments.0.comment', 'Quedó increíble')
            ->assertJsonPath('data.0.comments.0.user.name', 'Viewer');
    }

    public function test_react_toggles_like(): void
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $viewer = User::create(['name' => 'Viewer', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $viewer->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $token = 'test-social-react-token';
        MobileApiToken::create(['user_id' => (string) $viewer->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $barberUser = User::create(['name' => 'Barbero Muro 2', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Muro 2', 'activo' => true]);
        $work = Work::create(['barbero_id' => (string) $barberUser->id, 'title' => 'Corte', 'description' => 'x']);

        $this->withToken($token)->postJson("/api/v1/social/work/{$work->id}/react")
            ->assertOk()->assertJsonPath('status', 'added');

        $this->withToken($token)->postJson("/api/v1/social/work/{$work->id}/react")
            ->assertOk()->assertJsonPath('status', 'removed');
    }
}
