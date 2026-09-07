<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Storage::disk('public')->deleteDirectory('avatars');

        parent::tearDown();
    }

    public function test_authenticated_user_can_replace_their_own_avatar(): void
    {
        Storage::fake('public');
        $user = User::create(['name' => 'Perfil', 'email' => 'perfil@test.local', 'password' => 'password']);
        $token = $this->tokenFor($user, 'profile-avatar-token');

        $response = $this->withToken($token)->post('/api/v1/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.jpg', 10, 'image/jpeg'),
        ]);

        $response->assertOk()->assertJsonPath('message', 'Foto de perfil actualizada.');
        $this->assertStringContainsString('/storage/avatars/'.(string) $user->id.'/', $user->fresh()->avatar_url);
    }

    public function test_avatar_rejects_non_images(): void
    {
        $user = User::create(['name' => 'Perfil', 'email' => 'perfil-invalid@test.local', 'password' => 'password']);
        $token = $this->tokenFor($user, 'profile-avatar-invalid-token');

        $this->withToken($token)->withHeader('Accept', 'application/json')->post('/api/v1/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.txt', 10, 'text/plain'),
        ])->assertStatus(422);
    }

    private function tokenFor(User $user, string $plaintext): string
    {
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
    }
}
