<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Reaction;
use App\Models\Role;
use App\Models\SavedWork;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\Work;
use App\Models\WorkImage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Contenido de usuarios del muro (política de Google Play): reportar un comentario, bloquear a su
 * autor y que el administrador modere. El feed debe dejar de mostrar lo reportado y lo bloqueado.
 */
class CommentModerationApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        CommentReport::query()->delete();
        UserBlock::query()->delete();
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

    /** @return array{0: User, 1: string} usuario y su token Bearer */
    private function makeUser(string $name, string $role = 'cliente'): array
    {
        $roleModel = Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => $name, 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $roleModel->id]])->save();
        $token = 'tok-'.Str::uuid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        return [$user, $token];
    }

    private function makeWork(): Work
    {
        $barberUser = User::create(['name' => 'Barbero Muro', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Muro', 'activo' => true]);

        return Work::create(['barbero_id' => (string) $barberUser->id, 'title' => 'Fade limpio', 'description' => 'Trabajo de prueba']);
    }

    private function makeComment(Work $work, User $author, string $text = 'Buen trabajo'): Comment
    {
        return Comment::create(['work_id' => (string) $work->id, 'user_id' => (string) $author->id, 'comment' => $text]);
    }

    public function test_report_creates_a_pending_report_and_is_idempotent(): void
    {
        [$author] = $this->makeUser('Ana Autora');
        [, $token] = $this->makeUser('Rosa Reporta');
        $comment = $this->makeComment($this->makeWork(), $author, 'Texto ofensivo');

        $this->withToken($token)->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'ofensivo'])
            ->assertCreated();
        $this->withToken($token)->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'spam'])
            ->assertOk();

        $this->assertSame(1, CommentReport::query()->where('comment_id', (string) $comment->id)->count());
        $this->assertSame('pending', CommentReport::query()->first()->status);
        $this->assertSame('ofensivo', CommentReport::query()->first()->reason);
    }

    public function test_report_rejects_own_comment_invalid_reason_and_guests(): void
    {
        [$author, $authorToken] = $this->makeUser('Ana Autora');
        $comment = $this->makeComment($this->makeWork(), $author);

        $this->withToken($authorToken)->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'spam'])
            ->assertStatus(422);

        [, $token] = $this->makeUser('Rosa Reporta');
        $this->withToken($token)->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'inventado'])
            ->assertStatus(422);
        $this->withToken($token)->postJson("/api/v1/social/comment/{$comment->id}/report", [])
            ->assertStatus(422);

        // withToken() persiste durante toda la prueba: se limpia para que la llamada sea realmente de invitado.
        $this->flushHeaders();
        $this->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'spam'])->assertUnauthorized();
        $this->assertSame(0, CommentReport::query()->count());
    }

    public function test_feed_hides_reported_comments_only_for_the_reporter_and_flags_own_comments(): void
    {
        [$author, $authorToken] = $this->makeUser('Ana Autora');
        [, $reporterToken] = $this->makeUser('Rosa Reporta');
        [, $otherToken] = $this->makeUser('Otro Lector');
        $work = $this->makeWork();
        $reported = $this->makeComment($work, $author, 'Comentario reportado');
        $this->makeComment($work, $author, 'Comentario normal');

        $this->withToken($reporterToken)->postJson("/api/v1/social/comment/{$reported->id}/report", ['reason' => 'spam'])->assertCreated();

        $forReporter = $this->withToken($reporterToken)->getJson('/api/v1/social/feed')->assertOk();
        $forReporter->assertJsonPath('data.0.comments_count', 1);
        $this->assertSame(['Comentario normal'], collect($forReporter->json('data.0.comments'))->pluck('comment')->all());

        $forOther = $this->withToken($otherToken)->getJson('/api/v1/social/feed')->assertOk();
        $forOther->assertJsonPath('data.0.comments_count', 2);
        $this->assertSame([false, false], collect($forOther->json('data.0.comments'))->pluck('is_mine')->all());

        $forAuthor = $this->withToken($authorToken)->getJson('/api/v1/social/feed')->assertOk();
        $this->assertSame([true, true], collect($forAuthor->json('data.0.comments'))->pluck('is_mine')->all());
    }

    public function test_block_author_hides_their_comments_and_can_be_undone(): void
    {
        [$author] = $this->makeUser('Ana Autora');
        [$blocker, $blockerToken] = $this->makeUser('Beto Bloquea');
        [, $strangerToken] = $this->makeUser('Otro Lector');
        $work = $this->makeWork();
        $comment = $this->makeComment($work, $author, 'Molesto');
        $this->makeComment($work, $blocker, 'Mi comentario');

        $this->withToken($blockerToken)->postJson("/api/v1/social/comment/{$comment->id}/block-author")->assertCreated();
        $this->withToken($blockerToken)->postJson("/api/v1/social/comment/{$comment->id}/block-author")->assertOk();
        $this->assertSame(1, UserBlock::query()->count());

        $feed = $this->withToken($blockerToken)->getJson('/api/v1/social/feed')->assertOk();
        $this->assertSame(['Mi comentario'], collect($feed->json('data.0.comments'))->pluck('comment')->all());
        $this->assertCount(2, $this->withToken($strangerToken)->getJson('/api/v1/social/feed')->json('data.0.comments'));

        $list = $this->withToken($blockerToken)->getJson('/api/v1/social/blocks')->assertOk();
        $list->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ana A.');
        $blockId = $list->json('data.0.id');

        $this->withToken($strangerToken)->deleteJson("/api/v1/social/blocks/{$blockId}")->assertNotFound();
        $this->withToken($blockerToken)->deleteJson("/api/v1/social/blocks/{$blockId}")->assertOk();
        $this->assertSame(0, UserBlock::query()->count());
        $this->assertCount(2, $this->withToken($blockerToken)->getJson('/api/v1/social/feed')->json('data.0.comments'));
    }

    public function test_cannot_block_yourself(): void
    {
        [$author, $token] = $this->makeUser('Ana Autora');
        $comment = $this->makeComment($this->makeWork(), $author);

        $this->withToken($token)->postJson("/api/v1/social/comment/{$comment->id}/block-author")->assertStatus(422);
        $this->assertSame(0, UserBlock::query()->count());
    }

    public function test_admin_sees_pending_reports_grouped_by_comment(): void
    {
        [$author] = $this->makeUser('Ana Autora');
        [, $r1] = $this->makeUser('Rosa Reporta');
        [, $r2] = $this->makeUser('Raul Reporta');
        [, $adminToken] = $this->makeUser('Admin', 'administrador');
        $comment = $this->makeComment($this->makeWork(), $author, 'Texto problemático');

        $this->withToken($r1)->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'ofensivo'])->assertCreated();
        $this->withToken($r2)->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'spam'])->assertCreated();

        $this->withToken($adminToken)->getJson('/api/v1/admin/comment-reports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment_id', (string) $comment->id)
            ->assertJsonPath('data.0.comment', 'Texto problemático')
            ->assertJsonPath('data.0.reports_count', 2)
            ->assertJsonPath('data.0.reasons.ofensivo', 1)
            ->assertJsonPath('data.0.reasons.spam', 1)
            ->assertJsonPath('data.0.author.name', 'Ana Autora')
            ->assertJsonPath('data.0.author.comments_banned', false);
    }

    public function test_moderation_endpoints_are_admin_only(): void
    {
        [$author] = $this->makeUser('Ana Autora');
        [, $clientToken] = $this->makeUser('Rosa Reporta');
        $comment = $this->makeComment($this->makeWork(), $author);

        $this->withToken($clientToken)->getJson('/api/v1/admin/comment-reports')->assertForbidden();
        $this->withToken($clientToken)->postJson("/api/v1/admin/comment-reports/{$comment->id}/resolve", ['action' => 'remove'])->assertForbidden();
        $this->flushHeaders();
        $this->getJson('/api/v1/admin/comment-reports')->assertUnauthorized();
    }

    public function test_admin_remove_deletes_the_comment_and_closes_all_its_reports(): void
    {
        [$author] = $this->makeUser('Ana Autora');
        [, $r1] = $this->makeUser('Rosa Reporta');
        [, $r2] = $this->makeUser('Raul Reporta');
        [$admin, $adminToken] = $this->makeUser('Admin', 'administrador');
        $comment = $this->makeComment($this->makeWork(), $author, 'Retirar');
        $this->withToken($r1)->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'ofensivo'])->assertCreated();
        $this->withToken($r2)->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'ofensivo'])->assertCreated();

        $this->withToken($adminToken)->postJson("/api/v1/admin/comment-reports/{$comment->id}/resolve", ['action' => 'remove'])
            ->assertOk()->assertJsonPath('resolved_reports', 2);

        $this->assertNull(Comment::query()->where('_id', (string) $comment->id)->first());
        $this->assertSame(2, CommentReport::query()->where('status', 'removed')->count());
        $this->assertSame((string) $admin->id, (string) CommentReport::query()->first()->resolved_by);
        $this->assertFalse((bool) $author->fresh()->getAttribute('comments_banned_at'));
        $this->withToken($adminToken)->getJson('/api/v1/admin/comment-reports')->assertJsonCount(0, 'data');
    }

    public function test_admin_remove_and_ban_stops_the_author_from_commenting(): void
    {
        [$author, $authorToken] = $this->makeUser('Ana Autora');
        [, $reporterToken] = $this->makeUser('Rosa Reporta');
        [, $adminToken] = $this->makeUser('Admin', 'administrador');
        $work = $this->makeWork();
        $comment = $this->makeComment($work, $author, 'Insulto');
        $this->withToken($reporterToken)->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'ofensivo'])->assertCreated();

        $this->withToken($authorToken)->postJson("/api/v1/social/work/{$work->id}/comment", ['comment' => 'Otro antes del veto'])->assertCreated();

        $this->withToken($adminToken)->postJson("/api/v1/admin/comment-reports/{$comment->id}/resolve", ['action' => 'remove_and_ban'])->assertOk();

        $this->assertTrue((bool) $author->fresh()->getAttribute('comments_banned_at'));
        $this->withToken($authorToken)->postJson("/api/v1/social/work/{$work->id}/comment", ['comment' => 'Despues del veto'])->assertForbidden();
    }

    public function test_admin_dismiss_keeps_the_comment(): void
    {
        [$author] = $this->makeUser('Ana Autora');
        [, $reporterToken] = $this->makeUser('Rosa Reporta');
        [, $adminToken] = $this->makeUser('Admin', 'administrador');
        $comment = $this->makeComment($this->makeWork(), $author, 'Comentario válido');
        $this->withToken($reporterToken)->postJson("/api/v1/social/comment/{$comment->id}/report", ['reason' => 'otro'])->assertCreated();

        $this->withToken($adminToken)->postJson("/api/v1/admin/comment-reports/{$comment->id}/resolve", ['action' => 'dismiss'])->assertOk();

        $this->assertNotNull(Comment::query()->where('_id', (string) $comment->id)->first());
        $this->assertSame('dismissed', CommentReport::query()->first()->status);
    }

    public function test_resolve_validates_action_and_requires_pending_reports(): void
    {
        [$author] = $this->makeUser('Ana Autora');
        [, $adminToken] = $this->makeUser('Admin', 'administrador');
        $comment = $this->makeComment($this->makeWork(), $author);

        $this->withToken($adminToken)->postJson("/api/v1/admin/comment-reports/{$comment->id}/resolve", ['action' => 'borrar_todo'])->assertStatus(422);
        $this->withToken($adminToken)->postJson("/api/v1/admin/comment-reports/{$comment->id}/resolve", ['action' => 'remove'])->assertNotFound();
        $this->assertNotNull(Comment::query()->where('_id', (string) $comment->id)->first());
    }
}
