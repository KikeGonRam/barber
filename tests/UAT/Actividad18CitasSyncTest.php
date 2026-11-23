<?php

namespace Tests\UAT;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\Waitlist;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Actividad 18 (Desarrollo Móvil Integral): sincronización de Citas entre la
 * app Android y el backend. Reproduce las llamadas HTTP que hace la app
 * (UrbanBladeApi.kt: GET/POST/PATCH appointments) con el mismo tipo de token
 * Bearer, contra el Mongo local de pruebas, y deja las respuestas en
 * storage/logs/act18_evidencia.json para documentarlas.
 */
class Actividad18CitasSyncTest extends TestCase
{
    private User $cliente;

    private User $otroCliente;

    private User $admin;

    private Barber $barber;

    private Service $service;

    private array $evidencia = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = $this->makeUser('Admin Act18', 'administrador');
        $this->cliente = $this->makeUser('Cliente Act18', 'cliente');
        $this->otroCliente = $this->makeUser('Otro Cliente Act18', 'cliente');
        foreach ([$this->cliente, $this->otroCliente] as $u) {
            Client::create(['user_id' => (string) $u->id, 'telefono' => '5550001111', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        }

        $barberUser = User::create(['name' => 'Barbero Act18', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $this->barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Act18', 'activo' => true]);
        $this->service = Service::create(['nombre' => 'Corte clásico', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
    }

    protected function tearDown(): void
    {
        $file = storage_path('logs/act18_evidencia.json');
        $previa = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        file_put_contents(
            $file,
            json_encode(array_merge($previa, $this->evidencia), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        Waitlist::query()->delete();
        Appointment::withTrashed()->forceDelete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function makeUser(string $name, string $roleName): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => $name, 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        return $user;
    }

    private function tokenFor(User $user): string
    {
        $plain = Str::random(40);
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'act18', 'token_hash' => hash('sha256', $plain)]);

        return $plain;
    }

    private function note(string $step, $response): void
    {
        $this->evidencia[$step] = ['status' => $response->getStatusCode(), 'body' => $response->json()];
    }

    private function newBooking(array $overrides = []): array
    {
        return array_merge([
            'barber_id' => (string) $this->barber->id,
            'service_id' => (string) $this->service->id,
            'fecha' => now()->addDays(3)->format('Y-m-d'),
            'hora_inicio' => '11:00',
            'notas' => 'Cita creada desde la app (Actividad 18)',
        ], $overrides);
    }

    public function test_flujo_completo_de_sincronizacion(): void
    {
        $auth = ['Authorization' => 'Bearer '.$this->tokenFor($this->cliente)];

        // Prueba 1: consultar los registros existentes (el cliente aún no tiene citas)
        $r1 = $this->withHeaders($auth)->getJson('/api/v1/appointments');
        $this->note('1_consulta_inicial', $r1);
        $r1->assertOk();
        $antes = count($r1->json('data') ?? []);
        $this->assertSame(0, $antes);

        // Prueba 2: crear un nuevo registro desde la aplicación (POST)
        $r2 = $this->withHeaders($auth)->postJson('/api/v1/appointments', $this->newBooking());
        $this->note('2_registro_post', $r2);
        $r2->assertStatus(201);

        // Prueba 3: volver a consultar y comprobar que el registro está en el servicio remoto
        $r3 = $this->withHeaders($auth)->getJson('/api/v1/appointments');
        $this->note('3_reconsulta', $r3);
        $r3->assertOk();
        $this->assertCount($antes + 1, $r3->json('data'));
        $this->assertSame(1, Appointment::count(), 'La cita quedó persistida en la base de datos.');

        // Un cliente distinto no ve la cita (autorización por dueño)
        $otro = ['Authorization' => 'Bearer '.$this->tokenFor($this->otroCliente)];
        $r3b = $this->withHeaders($otro)->getJson('/api/v1/appointments');
        $this->note('3b_otro_cliente_no_la_ve', $r3b);
        $r3b->assertOk();
        $this->assertCount(0, $r3b->json('data'));

        // El administrador sí la ve
        $adminAuth = ['Authorization' => 'Bearer '.$this->tokenFor($this->admin)];
        $r3c = $this->withHeaders($adminAuth)->getJson('/api/v1/appointments');
        $this->note('3c_admin_la_ve', $r3c);
        $this->assertCount(1, $r3c->json('data'));

        // Actualización (PATCH de estado): solo personal autorizado
        $id = (string) Appointment::first()->code;
        $r6 = $this->withHeaders($auth)->patchJson("/api/v1/appointments/{$id}/status", ['estado' => 'confirmada']);
        $this->note('6a_patch_estado_como_cliente', $r6);
        $r6->assertStatus(403);

        $r7 = $this->withHeaders($adminAuth)->patchJson("/api/v1/appointments/{$id}/status", ['estado' => 'confirmada']);
        $this->note('6b_patch_estado_como_admin', $r7);
        $r7->assertOk();
        $this->assertSame('confirmada', Appointment::first()->estado);
    }

    public function test_informacion_incompleta_es_rechazada_con_422(): void
    {
        $auth = ['Authorization' => 'Bearer '.$this->tokenFor($this->cliente)];

        // Prueba 4: se omiten barbero, servicio y hora
        $r = $this->withHeaders($auth)->postJson('/api/v1/appointments', ['fecha' => now()->addDays(3)->format('Y-m-d')]);
        $this->note('4a_incompleto', $r);
        $r->assertStatus(422)->assertJsonValidationErrors(['barber_id', 'service_id', 'hora_inicio']);

        // Fecha en el pasado
        $r2 = $this->withHeaders($auth)->postJson('/api/v1/appointments', $this->newBooking(['fecha' => now()->subDay()->format('Y-m-d')]));
        $this->note('4b_fecha_pasada', $r2);
        $r2->assertStatus(422)->assertJsonValidationErrors(['fecha']);

        $this->assertSame(0, Appointment::count(), 'Ningún registro inválido se guardó.');
    }

    public function test_respuestas_fallidas_de_autenticacion_y_autorizacion(): void
    {
        // Prueba 5 (servidor): sin token y con token falso
        $r = $this->getJson('/api/v1/appointments');
        $this->note('5a_sin_token', $r);
        $r->assertStatus(401);

        $r2 = $this->withHeaders(['Authorization' => 'Bearer token-inventado'])->getJson('/api/v1/appointments');
        $this->note('5b_token_invalido', $r2);
        $r2->assertStatus(401);

        $r3 = $this->postJson('/api/v1/appointments', $this->newBooking());
        $this->note('5c_post_sin_token', $r3);
        $r3->assertStatus(401);
        $this->assertSame(0, Appointment::count());

        // El mensaje de error no revela datos sensibles
        $this->assertStringNotContainsString('mongodb', strtolower((string) $r2->getContent()));
        $this->assertStringNotContainsString('password', strtolower((string) $r2->getContent()));
    }
}
