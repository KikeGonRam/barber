<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class MobileApiParityAccessTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_admin_can_manage_services_crud_from_mobile_api(): void
    {
        $admin = $this->makeUserWithRole('administrador');
        $token = $this->tokenFor($admin);

        $created = $this->withBearerToken($token)
            ->postJson('/api/v1/services/manage', [
                'nombre' => 'Corte Premium API',
                'categoria' => 'corte',
                'precio' => 350,
                'duracion_min' => 45,
                'descripcion' => 'Servicio premium por API',
                'activo' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Servicio creado correctamente.')
            ->json('data.id');

        $this->withBearerToken($token)
            ->getJson('/api/v1/services/manage')
            ->assertOk()
            ->assertJsonPath('data.0.id', $created);

        $this->withBearerToken($token)
            ->putJson('/api/v1/services/manage/'.$created, [
                'nombre' => 'Corte Premium API Editado',
                'categoria' => 'corte',
                'precio' => 390,
                'duracion_min' => 50,
                'descripcion' => 'Servicio actualizado por API',
                'activo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Corte Premium API Editado');

        $this->withBearerToken($token)
            ->deleteJson('/api/v1/services/manage/'.$created)
            ->assertOk()
            ->assertJsonPath('message', 'Servicio eliminado correctamente.');
    }

    public function test_recepcionista_cannot_manage_services_from_mobile_api(): void
    {
        $recepcionista = $this->makeUserWithRole('recepcionista');
        $token = $this->tokenFor($recepcionista);

        $this->withBearerToken($token)
            ->getJson('/api/v1/services/manage')
            ->assertForbidden();

        $this->withBearerToken($token)
            ->postJson('/api/v1/services/manage', [
                'nombre' => 'Intento Recepcion',
                'categoria' => 'corte',
                'precio' => 200,
                'duracion_min' => 30,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_manage_users_crud_from_mobile_api(): void
    {
        $admin = $this->makeUserWithRole('administrador');
        $token = $this->tokenFor($admin);

        $created = $this->withBearerToken($token)
            ->postJson('/api/v1/users', [
                'name' => 'Nuevo Usuario API',
                'email' => 'nuevo.usuario.api@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'cliente',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Usuario creado correctamente.')
            ->json('data.id');

        $this->withBearerToken($token)
            ->getJson('/api/v1/users?q=Nuevo Usuario API')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $created,
                'name' => 'Nuevo Usuario API',
                'email' => 'nuevo.usuario.api@example.com',
            ]);

        $this->withBearerToken($token)
            ->putJson('/api/v1/users/'.$created, [
                'name' => 'Usuario API Editado',
                'email' => 'usuario.api.editado@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'barbero',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Usuario API Editado');

        $this->withBearerToken($token)
            ->deleteJson('/api/v1/users/'.$created)
            ->assertOk()
            ->assertJsonPath('message', 'Usuario eliminado correctamente.');
    }

    public function test_recepcionista_can_manage_clients_but_cannot_manage_users(): void
    {
        $recepcionista = $this->makeUserWithRole('recepcionista');
        $token = $this->tokenFor($recepcionista);

        $this->withBearerToken($token)
            ->postJson('/api/v1/clients', [
                'name' => 'Cliente Recepcion API',
                'email' => 'cliente.recepcion.api@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'telefono' => '555123456',
                'pref_in_app' => true,
                'pref_email' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Cliente creado correctamente.');

        $client = Client::query()->firstWhere('telefono', '555123456');

        $this->assertNotNull($client);

        $this->withBearerToken($token)
            ->putJson('/api/v1/clients/'.$client->id, [
                'name' => 'Cliente Recepcion API Editado',
                'email' => 'cliente.recepcion.api.editado@example.com',
                'telefono' => '555987654',
                'pref_in_app' => true,
                'pref_email' => false,
                'pref_sms' => false,
                'pref_whatsapp' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.telefono', '555987654');

        $this->withBearerToken($token)
            ->deleteJson('/api/v1/clients/'.$client->id)
            ->assertOk()
            ->assertJsonPath('message', 'Cliente eliminado correctamente.');

        $this->withBearerToken($token)
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_admin_can_manage_settings_and_reports_from_mobile_api(): void
    {
        $admin = $this->makeUserWithRole('administrador');
        $token = $this->tokenFor($admin);

        $this->withBearerToken($token)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'nombre', 'politica_cancelacion', 'maintenance_mode']]);

        $this->withBearerToken($token)
            ->putJson('/api/v1/settings', [
                'nombre' => 'UrbanBlade QA',
                'direccion' => 'Direccion de prueba 123',
                'telefono' => '555000111',
                'horario_apertura' => '09:00',
                'horario_cierre' => '20:00',
                'politica_cancelacion' => 12,
                'instagram' => '@urbanblade',
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'UrbanBlade QA')
            ->assertJsonPath('data.politica_cancelacion', 12);

        $this->withBearerToken($token)
            ->postJson('/api/v1/settings/maintenance')
            ->assertOk()
            ->assertJsonPath('data.maintenance_mode', true);

        $this->withBearerToken($token)
            ->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonPath('formats.0', 'json');

        $this->withBearerToken($token)
            ->getJson('/api/v1/reports/citas/json')
            ->assertOk()
            ->assertJsonStructure(['title', 'headings', 'keys', 'rows', 'filters']);
    }

    public function test_non_admin_cannot_access_admin_reports_or_settings(): void
    {
        $recepcionista = $this->makeUserWithRole('recepcionista');
        $token = $this->tokenFor($recepcionista);

        $this->withBearerToken($token)->getJson('/api/v1/reports')->assertForbidden();
        $this->withBearerToken($token)->getJson('/api/v1/settings')->assertForbidden();
    }

    public function test_authenticated_user_can_list_and_mark_notifications_as_read(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        $token = $this->tokenFor($cliente);

        DatabaseNotification::query()->create([
            'id' => (string) str()->uuid(),
            'type' => 'App\\Notifications\\AppointmentNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $cliente->id,
            'data' => [
                'title' => 'Nueva notificacion',
                'message' => 'Tu cita fue confirmada',
            ],
            'read_at' => null,
        ]);

        $this->withBearerToken($token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread', 1)
            ->assertJsonPath('data.0.data.title', 'Nueva notificacion');

        $this->withBearerToken($token)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread', 0);

        $this->assertSame(0, $cliente->fresh()->unreadNotifications()->count());
    }

    private function withBearerToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        if ($role === 'cliente') {
            Client::query()->firstOrCreate([
                'user_id' => $user->id,
            ]);
        }

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->issueMobileApiToken('tests-mobile')['token'];
    }
}
