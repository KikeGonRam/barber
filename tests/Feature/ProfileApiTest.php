<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        Appointment::query()->delete();
        Client::query()->delete();
        Barber::query()->delete();
        Service::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Storage::disk('public')->deleteDirectory('avatars');
        Storage::disk('public')->deleteDirectory('barbers');

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

    public function test_authenticated_client_can_view_their_profile(): void
    {
        $role = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Carlos Cliente',
            'email' => 'carlos.cliente@test.local',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        $client = Client::create([
            'user_id' => (string) $user->id,
            'telefono' => '5511223344',
            'fecha_nacimiento' => '1995-05-15',
        ]);

        $token = $this->tokenFor($user, 'client-profile-view-token');

        $response = $this->withToken($token)->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('user.name', 'Carlos Cliente')
            ->assertJsonPath('user.email', 'carlos.cliente@test.local')
            ->assertJsonPath('user.client_id', (string) $client->id)
            ->assertJsonPath('user.client.telefono', '5511223344')
            ->assertJsonPath('user.client.fecha_nacimiento', '1995-05-15');
    }

    public function test_client_can_update_profile_and_client_details(): void
    {
        $role = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Original Name',
            'email' => 'original@test.local',
            'password' => 'password',
        ]);
        $user->assignRole($role);
        $client = Client::create([
            'user_id' => (string) $user->id,
            'telefono' => '5500000000',
            'fecha_nacimiento' => '1990-01-01',
        ]);

        $token = $this->tokenFor($user, 'client-profile-update-token');

        $response = $this->withToken($token)->putJson('/api/v1/profile', [
            'name' => 'Nuevo Nombre',
            'email' => 'nuevo.email@test.local',
            'telefono' => '5599887766',
            'fecha_nacimiento' => '1992-06-20',
        ]);

        $response->assertOk()->assertJsonPath('message', 'Perfil actualizado exitosamente');

        $user->refresh();
        $client->refresh();

        $this->assertSame('Nuevo Nombre', $user->name);
        $this->assertSame('nuevo.email@test.local', $user->email);
        $this->assertSame('5599887766', $client->telefono);
        $this->assertSame('1992-06-20', substr((string) $client->fecha_nacimiento, 0, 10));
    }

    public function test_profile_update_validates_email_uniqueness(): void
    {
        $user1 = User::create(['name' => 'User One', 'email' => 'one@test.local', 'password' => 'password']);
        $user2 = User::create(['name' => 'User Two', 'email' => 'two@test.local', 'password' => 'password']);

        $token = $this->tokenFor($user2, 'client-unique-email-token');

        $this->withToken($token)->putJson('/api/v1/profile', [
            'email' => 'one@test.local',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_update_password_with_correct_current_password(): void
    {
        $user = User::create([
            'name' => 'Password User',
            'email' => 'pass@test.local',
            'password' => Hash::make('old-password-123'),
        ]);
        $token = $this->tokenFor($user, 'password-update-token');

        $response = $this->withToken($token)->putJson('/api/v1/profile/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-secure-password-456',
            'password_confirmation' => 'new-secure-password-456',
        ]);

        $response->assertOk()->assertJsonPath('message', 'Contraseña actualizada exitosamente');
        $this->assertTrue(Hash::check('new-secure-password-456', $user->fresh()->password));
    }

    public function test_update_password_rejects_incorrect_current_password(): void
    {
        $user = User::create([
            'name' => 'Wrong Password User',
            'email' => 'wrong-pass@test.local',
            'password' => Hash::make('real-password-123'),
        ]);
        $token = $this->tokenFor($user, 'wrong-password-token');

        $this->withToken($token)->putJson('/api/v1/profile/password', [
            'current_password' => 'incorrect-guess',
            'password' => 'new-secure-password-456',
            'password_confirmation' => 'new-secure-password-456',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'La contraseña actual es incorrecta.');
    }

    public function test_user_can_save_expo_push_token(): void
    {
        $user = User::create(['name' => 'Push User', 'email' => 'push@test.local', 'password' => 'password']);
        $token = $this->tokenFor($user, 'push-token-test');

        $response = $this->withToken($token)->postJson('/api/v1/profile/push-token', [
            'token' => 'ExponentPushToken[Abc123Xyz]',
        ]);

        $response->assertOk()->assertJsonPath('message', 'Token registrado');
        $this->assertSame('ExponentPushToken[Abc123Xyz]', $user->fresh()?->getAttribute('expo_push_token'));
    }

    public function test_user_can_delete_their_own_account_with_correct_password(): void
    {
        $role = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Delete Me',
            'email' => 'delete-me@test.local',
            'password' => Hash::make('delete-password-123'),
        ]);
        $user->assignRole($role);
        $token = $this->tokenFor($user, 'delete-account-token');

        $response = $this->withToken($token)->deleteJson('/api/v1/profile', [
            'password' => 'delete-password-123',
        ]);

        $response->assertOk()->assertJsonPath('message', 'Cuenta eliminada exitosamente');

        $this->assertSoftDeleted('users', ['_id' => $user->id]);
        $this->assertSame(0, MobileApiToken::where('user_id', (string) $user->id)->count());
    }

    public function test_delete_account_fails_with_wrong_password(): void
    {
        $user = User::create([
            'name' => 'Delete Safe',
            'email' => 'delete-safe@test.local',
            'password' => Hash::make('delete-password-123'),
        ]);
        $token = $this->tokenFor($user, 'delete-safe-token');

        $this->withToken($token)->deleteJson('/api/v1/profile', [
            'password' => 'wrong-pass',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Contraseña incorrecta.');
    }

    public function test_admin_cannot_delete_their_own_account_via_profile(): void
    {
        $role = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => 'Admin Boss',
            'email' => 'admin-boss@test.local',
            'password' => Hash::make('admin-password-123'),
        ]);
        $admin->assignRole($role);
        $token = $this->tokenFor($admin, 'admin-delete-blocked-token');

        $this->withToken($token)->deleteJson('/api/v1/profile', [
            'password' => 'admin-password-123',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Los administradores no pueden eliminar su propia cuenta desde el perfil.');
    }

    public function test_client_cannot_delete_account_with_active_appointments(): void
    {
        $roleClient = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $roleBarber = Role::firstOrCreate(['name' => 'barbero', 'guard_name' => 'web']);

        $clientUser = User::create(['name' => 'Client With Appt', 'email' => 'cwa@test.local', 'password' => Hash::make('client-pass')]);
        $clientUser->assignRole($roleClient);
        $client = Client::create(['user_id' => (string) $clientUser->id]);

        $barberUser = User::create(['name' => 'Barber Pro', 'email' => 'bp@test.local', 'password' => 'password']);
        $barberUser->assignRole($roleBarber);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barber Pro', 'activo' => true]);

        $service = Service::create(['nombre' => 'Corte Moderno', 'precio' => 250, 'duracion_minutos' => 45, 'activo' => true]);

        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => Carbon::tomorrow()->toDateString(),
            'hora_inicio' => '11:00:00',
            'hora_fin' => '11:45:00',
            'estado' => 'confirmada',
        ]);

        $token = $this->tokenFor($clientUser, 'client-has-appt-token');

        $this->withToken($token)->deleteJson('/api/v1/profile', [
            'password' => 'client-pass',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'No puedes eliminar tu cuenta con citas activas pendientes. Cancela tus citas primero.');
    }

    public function test_barber_can_view_and_update_barber_profile_and_bio(): void
    {
        $role = Role::firstOrCreate(['name' => 'barbero', 'guard_name' => 'web']);
        $user = User::create(['name' => 'Master Barber', 'email' => 'master@test.local', 'password' => 'password']);
        $user->assignRole($role);

        $barber = Barber::create([
            'user_id' => (string) $user->id,
            'nombre' => 'Master Barber',
            'especialidades' => 'Degradado, Barba',
            'descripcion' => 'Especialista en cortes urbanos.',
            'activo' => true,
        ]);

        $token = $this->tokenFor($user, 'barber-profile-token');

        // View barber profile via /api/v1/barber/me
        $resView = $this->withToken($token)->getJson('/api/v1/barber/me');
        $resView->assertOk()
            ->assertJsonPath('name', 'Master Barber')
            ->assertJsonPath('especialidades', 'Degradado, Barba')
            ->assertJsonPath('descripcion', 'Especialista en cortes urbanos.');

        // Update bio
        $resBio = $this->withToken($token)->putJson('/api/v1/barber/bio', [
            'especialidades' => 'Degradado, Barba, Tintes',
            'descripcion' => 'Actualizada biografía.',
        ]);
        $resBio->assertOk()
            ->assertJsonPath('especialidades', 'Degradado, Barba, Tintes')
            ->assertJsonPath('descripcion', 'Actualizada biografía.');

        // Non-barber gets rejected by role middleware
        $clientUser = User::create(['name' => 'No Barber', 'email' => 'nobarber@test.local', 'password' => 'password']);
        $clientToken = $this->tokenFor($clientUser, 'nobarber-token');

        $this->withToken($clientToken)->getJson('/api/v1/barber/me')->assertStatus(403);
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
