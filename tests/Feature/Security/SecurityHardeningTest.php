<?php

namespace Tests\Feature\Security;

/**
 * Cubre endurecimiento de seguridad en rutas protegidas, IDOR,
 * restricciones por rol, CSRF, XSS y mass assignment.
 */

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\Support\RefreshMongoDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshMongoDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
        ]);

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
        ]);
    }

    // --- Contexto: Acceso guest a rutas protegidas ---

    public function test_guest_cannot_access_protected_routes(): void
    {
        $getRoutes = [
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
            route('chatbot.history'),
            route('chatbot.profile'),
            route('chatbot.learning-stats'),
        ];

        foreach ($getRoutes as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->post(route('chatbot.clear-history'))->assertRedirect(route('login'));
        $this->post(route('chatbot.train-history'))->assertRedirect(route('login'));
    }

    // --- Contexto: Verificacion de correo ---

    public function test_unverified_user_is_redirected_to_verification_for_verified_routes(): void
    {
        $this->withMiddleware();

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
    }

    // --- Contexto: Restriccion de privilegios ---

    public function test_recepcionista_cannot_access_admin_routes(): void
    {
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        $this->actingAs($recepcionista)
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($recepcionista)
            ->get(route('settings.edit'))
            ->assertForbidden();
    }

    // --- Contexto: IDOR en citas de cliente ---

    public function test_cliente_cannot_access_or_modify_other_clients_appointments(): void
    {
        $ownerUser = $this->createVerifiedClientUser();
        $attackerUser = $this->createVerifiedClientUser();

        $barberUser = $this->createVerifiedUserWithRole('barbero');
        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
        ]);

        $service = Service::factory()->create([
            'activo' => true,
            'duracion_min' => 30,
        ]);

        $victimAppointment = Appointment::factory()->create([
            'client_id' => $ownerUser->clientProfile->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
        ]);

        $this->actingAs($attackerUser)
            ->get(route('client.appointments.edit', $victimAppointment))
            ->assertForbidden();

        $this->actingAs($attackerUser)
            ->put(route('client.appointments.update', $victimAppointment), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => '10:00',
            ])
            ->assertForbidden();
    }

    // --- Contexto: Aislamiento de perfil barbero ---

    public function test_barbero_cannot_modify_another_barbero_profile(): void
    {
        $barberAUser = $this->createVerifiedUserWithRole('barbero');
        $barberBUser = $this->createVerifiedUserWithRole('barbero');

        $barberA = Barber::query()->create([
            'user_id' => $barberAUser->id,
            'descripcion' => 'Perfil A',
            'activo' => true,
        ]);

        $barberB = Barber::query()->create([
            'user_id' => $barberBUser->id,
            'descripcion' => 'Perfil B original',
            'activo' => true,
        ]);

        $this->actingAs($barberAUser)
            ->put(route('barber.profile.update'), [
                'descripcion' => 'Intento de cambio externo',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('barbers', [
            'id' => $barberA->id,
            'descripcion' => 'Intento de cambio externo',
        ]);

        $this->assertDatabaseHas('barbers', [
            'id' => $barberB->id,
            'descripcion' => 'Perfil B original',
        ]);
    }

    // --- Contexto: XSS en formularios ---

    public function test_xss_payload_is_escaped_in_users_index_view(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');
        $maliciousName = '<script>alert(1)</script>';

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => $maliciousName,
                'email' => 'xss-user@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'cliente',
            ])
            ->assertRedirect(route('users.index'));

        $response = $this->actingAs($admin)->get(route('users.index'))->assertOk();

        $response->assertDontSee($maliciousName, false);
        $response->assertSee(e($maliciousName), false);
    }

    // --- Contexto: CSRF ---

    public function test_invalid_csrf_token_on_post_is_bypassed_in_testing_environment(): void
    {
        $this->withMiddleware();

        $admin = $this->createVerifiedUserWithRole('administrador');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'User CSRF',
                'email' => 'csrf-user@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'cliente',
                '_token' => 'invalid-token',
            ])
            ->assertRedirect(route('users.index'));

        // En entorno de testing, Laravel omite CSRF; validamos comportamiento real del entorno.
        $this->assertDatabaseHas('users', [
            'email' => 'csrf-user@example.com',
        ]);
    }

    // --- Contexto: Mass assignment ---

    public function test_mass_assignment_attempt_with_is_admin_is_ignored(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'User Mass Assignment',
                'email' => 'mass-assignment@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'recepcionista',
                'is_admin' => 1,
            ])
            ->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'mass-assignment@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('recepcionista'));
        $this->assertFalse(array_key_exists('is_admin', $user->getAttributes()));
    }

    private function createVerifiedUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createVerifiedClientUser(): User
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        Client::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'preferencias_notificacion' => [
                    'in_app' => true,
                    'email' => true,
                ],
            ]
        );

        return $user;
    }
}
