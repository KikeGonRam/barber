<?php

namespace Tests\Feature\Social;

use App\Models\User;
use App\Models\Work;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class SocialFeedTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_feed_can_be_viewed_by_a_guest(): void
    {
        Work::factory()->count(2)->create();

        $this->get('/descubrir')->assertOk();
    }

    public function test_authenticated_user_can_react_to_a_work(): void
    {
        $user = User::factory()->create();
        $work = Work::factory()->create();

        $response = $this->actingAs($user)->postJson("/social/work/{$work->id}/react");

        $response->assertOk();
        $response->assertJson(['status' => 'added']);
        $this->assertDatabaseHas('reactions', ['work_id' => (string) $work->id, 'user_id' => (string) $user->id]);
    }

    public function test_reacting_twice_removes_the_reaction(): void
    {
        $user = User::factory()->create();
        $work = Work::factory()->create();

        $this->actingAs($user)->postJson("/social/work/{$work->id}/react");
        $response = $this->actingAs($user)->postJson("/social/work/{$work->id}/react");

        $response->assertJson(['status' => 'removed']);
        $this->assertDatabaseMissing('reactions', ['work_id' => (string) $work->id, 'user_id' => (string) $user->id]);
    }

    public function test_authenticated_user_can_save_a_work(): void
    {
        $user = User::factory()->create();
        $work = Work::factory()->create();

        $response = $this->actingAs($user)->postJson("/social/work/{$work->id}/save");

        $response->assertOk();
        $response->assertJson(['status' => 'added']);
        $this->assertDatabaseHas('saved_works', ['work_id' => (string) $work->id, 'user_id' => (string) $user->id]);
    }

    public function test_authenticated_user_can_comment_on_a_work(): void
    {
        $user = User::factory()->create();
        $work = Work::factory()->create();

        $response = $this->actingAs($user)
            ->from('/descubrir')
            ->post("/social/work/{$work->id}/comment", ['comment' => 'Excelente corte!']);

        $response->assertRedirect('/descubrir');
        $this->assertDatabaseHas('comments', ['work_id' => (string) $work->id, 'comment' => 'Excelente corte!']);
    }

    public function test_comment_requires_text(): void
    {
        $user = User::factory()->create();
        $work = Work::factory()->create();

        $response = $this->actingAs($user)->post("/social/work/{$work->id}/comment", ['comment' => '']);

        $response->assertSessionHasErrors('comment');
    }

    public function test_guest_cannot_react_to_a_work(): void
    {
        $work = Work::factory()->create();

        $this->post("/social/work/{$work->id}/react")->assertRedirect('/login');
    }
}
