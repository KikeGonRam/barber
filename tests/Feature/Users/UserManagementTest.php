<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_admin_can_open_users_index(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk();
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Recepcion Demo',
                'email' => 'recepcion.demo@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'recepcionista',
            ])
            ->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'recepcion.demo@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('recepcionista'));
    }

    public function test_recepcionista_cannot_access_users_index(): void
    {
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        $this->actingAs($recepcionista)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    private function createVerifiedUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user->assignRole($role);

        return $user;
    }
}
