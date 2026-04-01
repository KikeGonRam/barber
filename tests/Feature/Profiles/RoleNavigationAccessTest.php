<?php

namespace Tests\Feature\Profiles;

use App\Models\Barber;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleNavigationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_sees_admin_navigation_and_can_access_admin_sections(): void
    {
        $admin = $this->makeUserWithRole('administrador');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operacion')
            ->assertSee('Gestion')
            ->assertSee('Clientes')
            ->assertSee('Citas')
            ->assertSee('Pagos')
            ->assertSee('Barberos')
            ->assertSee('Servicios')
            ->assertSee('Productos')
            ->assertSee('Reportes')
            ->assertSee('Configuracion')
            ->assertSee('Logs')
            ->assertDontSee('Mi agenda')
            ->assertDontSee('Mis citas');

        $this->actingAs($admin)->get(route('services.index'))->assertOk();
        $this->actingAs($admin)->get(route('inventory.products.index'))->assertOk();
        $this->actingAs($admin)->get(route('reports.index'))->assertOk();
        $this->actingAs($admin)->get(route('clients.index'))->assertOk();
        $this->actingAs($admin)->get(route('barbers.index'))->assertOk();
        $this->actingAs($admin)->get(route('settings.edit'))->assertOk();
        $this->actingAs($admin)->get(route('logs.index'))->assertOk();
    }

    public function test_recepcionista_sees_operational_navigation_without_admin_management_links(): void
    {
        $recepcionista = $this->makeUserWithRole('recepcionista');

        $this->actingAs($recepcionista)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operacion')
            ->assertSee('Clientes')
            ->assertSee('Citas')
            ->assertSee('Pagos')
            ->assertSee('Movimientos')
            ->assertDontSee('>Gestion<', false)
            ->assertDontSee('Servicios')
            ->assertDontSee('Productos')
            ->assertDontSee('Reportes')
            ->assertDontSee('Configuracion')
            ->assertDontSee('Logs')
            ->assertDontSee('Mi agenda')
            ->assertDontSee('Mis citas');

        $this->actingAs($recepcionista)->get(route('appointments.index'))->assertOk();
        $this->actingAs($recepcionista)->get(route('payments.index'))->assertOk();
        $this->actingAs($recepcionista)->get(route('inventory.movements.index'))->assertOk();
        $this->actingAs($recepcionista)->get(route('clients.index'))->assertOk();
        $this->actingAs($recepcionista)->get(route('services.index'))->assertForbidden();
        $this->actingAs($recepcionista)->get(route('reports.index'))->assertForbidden();
    }

    public function test_barbero_sees_barber_navigation_and_access_is_limited_to_barber_portal(): void
    {
        $barbero = $this->makeUserWithRole('barbero');

        Barber::query()->create([
            'user_id' => $barbero->id,
            'activo' => true,
        ]);

        $this->actingAs($barbero)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Profesional')
            ->assertSee('Mi Agenda')
            ->assertDontSee('Operacion')
            ->assertDontSee('>Gestion<', false)
            ->assertDontSee('Mis citas');

        $this->actingAs($barbero)->get(route('barber.agenda'))->assertOk();
        $this->actingAs($barbero)->get(route('appointments.index'))->assertForbidden();
        $this->actingAs($barbero)->get(route('reports.index'))->assertForbidden();
    }

    public function test_cliente_sees_client_navigation_and_access_is_limited_to_client_portal(): void
    {
        $cliente = $this->makeUserWithRole('cliente');

        Client::query()->create([
            'user_id' => $cliente->id,
            'preferencias_notificacion' => ['in_app' => true, 'email' => true],
        ]);

        $this->actingAs($cliente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cliente')
            ->assertSee('Mis Citas')
            ->assertDontSee('Operacion')
            ->assertDontSee('>Gestion<', false)
            ->assertDontSee('Mi agenda');

        $this->actingAs($cliente)->get(route('client.appointments.index'))->assertOk();
        $this->actingAs($cliente)->get(route('appointments.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('barber.agenda'))->assertForbidden();
    }

    public function test_notification_badge_is_hidden_when_user_has_no_unread_notifications(): void
    {
        $admin = $this->makeUserWithRole('administrador');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('bg-red-500 h-4 w-4', false);
    }

    public function test_notification_badge_shows_unread_count_when_notifications_exist(): void
    {
        $admin = $this->makeUserWithRole('administrador');

        DatabaseNotification::query()->create([
            'id' => (string) str()->uuid(),
            'type' => 'App\\Notifications\\AppointmentNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'data' => [
                'title' => 'Cita creada',
                'message' => 'Tienes una nueva cita',
            ],
            'read_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('bg-red-500 h-4 w-4', false)
            ->assertSee('>1<', false);
    }

    public function test_mobile_navigation_toggle_markup_is_present_on_dashboard(): void
    {
        $admin = $this->makeUserWithRole('administrador');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('x-data="{ open: false }"', false)
            ->assertSee('aria-label="Abrir menu"', false);
    }

    public function test_mark_all_read_endpoint_marks_user_notifications_as_read(): void
    {
        $admin = $this->makeUserWithRole('administrador');

        DatabaseNotification::query()->create([
            'id' => (string) str()->uuid(),
            'type' => 'App\\Notifications\\AppointmentNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'data' => [
                'title' => 'Recordatorio',
                'message' => 'Tu cita es mañana',
            ],
            'read_at' => null,
        ]);

        $this->assertSame(1, $admin->unreadNotifications()->count());

        $this->actingAs($admin)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('bg-red-500 h-4 w-4', false);
    }

    private function makeUserWithRole(string $roleName): User
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
}
