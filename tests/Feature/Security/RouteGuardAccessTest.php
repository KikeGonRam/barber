<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RouteGuardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_protected_routes(): void
    {
        $routes = [
            route('dashboard'),
            route('appointments.index'),
            route('reports.index'),
            route('clients.index'),
            route('barbers.index'),
            route('settings.edit'),
            route('logs.index'),
            route('client.appointments.index'),
            route('barber.agenda'),
            route('notifications.index'),
            route('profile.edit'),
        ];

        foreach ($routes as $url) {
            $this->get($url)
                ->assertRedirect(route('login'));
        }
    }

    public function test_unverified_user_is_redirected_to_verification_on_verified_routes(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'recepcionista',
            'guard_name' => 'web',
        ]);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_can_access_auth_only_profile_and_notifications_routes(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk();
    }
}
