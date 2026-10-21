<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * GET /api/v1/appointments/{código}: el detalle completo de una cita para la ventana de los paneles. El personal
 * ve cualquier cita (con el contacto del cliente); el barbero y el cliente, solo las suyas y sin el contacto.
 */
class AppointmentDetailApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Payment::query()->delete();
        Appointment::withTrashed()->forceDelete();
        Service::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function user(string $role, string $name): User
    {
        $roleModel = Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => $name, 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $roleModel->id]])->save();

        return $user;
    }

    private function token(User $user): string
    {
        $plain = 'tok-'.Str::uuid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain)]);

        return $plain;
    }

    /** @return array{0: Appointment, 1: User, 2: User} cita, usuario del cliente y usuario del barbero */
    private function cita(): array
    {
        $clientUser = $this->user('cliente', 'Cliente Dueño');
        $barberUser = $this->user('barbero', 'Barbero Dueño');
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5512345678', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Dueño', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte a Navaja', 'precio' => 260, 'duracion' => 45, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '12:30:00',
            'hora_fin' => '13:15:00',
            'estado' => 'completada',
            'notas' => 'Prefiere el degradado bajo',
            'precio_cobrado' => 260,
        ]);
        Payment::create(['appointment_id' => (string) $appointment->id, 'monto' => 260, 'propina' => 40, 'metodo_pago' => 'tarjeta']);

        return [$appointment, $clientUser, $barberUser];
    }

    public function test_el_personal_ve_el_detalle_completo_con_el_contacto_del_cliente(): void
    {
        [$appointment, $clientUser] = $this->cita();
        $code = $appointment->getAttribute('code');
        $admin = $this->user('administrador', 'Admin');

        $response = $this->withToken($this->token($admin))->getJson("/api/v1/appointments/{$code}");

        $response->assertOk()
            ->assertJsonPath('data.code', $appointment->getAttribute('code'))
            ->assertJsonPath('data.estado', 'completada')
            ->assertJsonPath('data.service.nombre', 'Corte a Navaja')
            ->assertJsonPath('data.client.user.name', 'Cliente Dueño')
            ->assertJsonPath('data.barber.user.name', 'Barbero Dueño')
            ->assertJsonPath('data.notas', 'Prefiere el degradado bajo')
            ->assertJsonPath('data.contacto.email', $clientUser->email)
            ->assertJsonPath('data.contacto.telefono', '5512345678')
            ->assertJsonCount(1, 'data.pagos')
            ->assertJsonPath('data.pagos.0.metodo_pago', 'tarjeta')
            ->assertJsonPath('data.pagos.0.propina', 40);
        $this->assertNotNull($response->json('data.creada_en'));
    }

    public function test_el_barbero_de_la_cita_la_ve_sin_el_contacto(): void
    {
        [$appointment, , $barberUser] = $this->cita();
        $code = $appointment->getAttribute('code');

        $this->withToken($this->token($barberUser))->getJson("/api/v1/appointments/{$code}")
            ->assertOk()
            ->assertJsonPath('data.contacto', null)
            ->assertJsonPath('data.service.nombre', 'Corte a Navaja');
    }

    public function test_el_cliente_dueno_la_ve_sin_el_contacto(): void
    {
        [$appointment, $clientUser] = $this->cita();
        $code = $appointment->getAttribute('code');

        $this->withToken($this->token($clientUser))->getJson("/api/v1/appointments/{$code}")
            ->assertOk()
            ->assertJsonPath('data.contacto', null);
    }

    public function test_otro_barbero_u_otro_cliente_no_pueden_verla(): void
    {
        [$appointment] = $this->cita();
        $code = $appointment->getAttribute('code');
        $otherBarber = $this->user('barbero', 'Otro Barbero');
        Barber::create(['user_id' => (string) $otherBarber->id, 'nombre' => 'Otro Barbero', 'activo' => true]);
        $otherClient = $this->user('cliente', 'Otro Cliente');
        Client::create(['user_id' => (string) $otherClient->id, 'telefono' => '5500000000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $this->withToken($this->token($otherBarber))->getJson("/api/v1/appointments/{$code}")->assertForbidden();
        $this->withToken($this->token($otherClient))->getJson("/api/v1/appointments/{$code}")->assertForbidden();
    }

    public function test_el_ingeniero_no_ve_citas_individuales_y_un_codigo_inexistente_es_404(): void
    {
        [$appointment] = $this->cita();
        $code = $appointment->getAttribute('code');
        $engineer = $this->user('ingeniero', 'Ingeniero');
        $admin = $this->user('administrador', 'Admin');

        $this->withToken($this->token($engineer))->getJson("/api/v1/appointments/{$code}")->assertForbidden();
        $this->withToken($this->token($admin))->getJson('/api/v1/appointments/no-existe')->assertNotFound();
    }
}
