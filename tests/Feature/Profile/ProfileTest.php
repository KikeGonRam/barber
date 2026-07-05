<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Nombre Actualizado',
                'email' => $user->email,
            ]);

        $response->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Nombre Actualizado', $user->name);
    }

    public function test_email_verification_status_is_reset_when_email_changes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'nuevo-correo@example.com',
            ]);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('nuevo-correo@example.com', $user->email);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertTrue($user->fresh()->trashed());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'contraseña-incorrecta',
            ]);

        $response->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($user->fresh());
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }
}
