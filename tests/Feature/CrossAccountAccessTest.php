<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\Waitlist;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * IDOR / acceso entre cuentas (auditoría de seguridad, 2026-10-10).
 *
 * El cliente B, el barbero B y un visitante sin token intentan leer o modificar los recursos del
 * cliente A (cita, ticket, depósitos, pago, pedido, lista de espera, notificaciones). Cada intento
 * debe terminar en 401/403/404 **y** dejar el dato intacto. Un caso de control con el dueño
 * verifica que las rutas existen y responden: así una ruta mal escrita no hace pasar la prueba
 * por un 404 que no viene de la autorización.
 *
 * Correr solo con `.\test.ps1` (ver TestCase: aborta si la base no es barber_db_test).
 */
class CrossAccountAccessTest extends TestCase
{
    private Client $clientA;

    private Barber $barberA;

    private Service $service;

    private Appointment $appointmentA;

    private Payment $paymentA;

    private Order $orderA;

    private Waitlist $waitlistA;

    private User $userA;

    private string $tokenClientA = 'idor-token-client-a';

    private string $tokenClientB = 'idor-token-client-b';

    private string $tokenBarberB = 'idor-token-barber-b';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $clientRole = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $barberRole = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();

        $this->userA = $this->makeUser('Cliente A', $clientRole, $this->tokenClientA);
        $this->clientA = Client::create(['user_id' => (string) $this->userA->id, 'telefono' => '5550001111', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $userB = $this->makeUser('Cliente B', $clientRole, $this->tokenClientB);
        Client::create(['user_id' => (string) $userB->id, 'telefono' => '5550002222', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $barberUserA = User::create(['name' => 'Barbero A', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barberUserA->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $barberRole->id]])->save();
        $this->barberA = Barber::create(['user_id' => (string) $barberUserA->id, 'nombre' => 'Barbero A', 'activo' => true]);

        $barberUserB = $this->makeUser('Barbero B', $barberRole, $this->tokenBarberB);
        Barber::create(['user_id' => (string) $barberUserB->id, 'nombre' => 'Barbero B', 'activo' => true]);

        $this->service = Service::create(['nombre' => 'Corte IDOR', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        $this->appointmentA = Appointment::create([
            'client_id' => (string) $this->clientA->id,
            'barber_id' => (string) $this->barberA->id,
            'service_id' => (string) $this->service->id,
            'fecha' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $this->paymentA = Payment::create([
            'appointment_id' => (string) $this->appointmentA->id,
            'monto' => 200,
            'metodo_pago' => 'efectivo',
            'estado' => Payment::ESTADO_VERIFICADO,
        ]);

        $this->orderA = Order::create(['client_id' => (string) $this->clientA->id, 'folio' => 'P-IDOR01', 'items' => [], 'total' => 100, 'estado' => 'pendiente', 'tipo' => 'tienda']);

        $this->waitlistA = Waitlist::create([
            'client_id' => (string) $this->clientA->id,
            'barber_id' => (string) $this->barberA->id,
            'service_id' => (string) $this->service->id,
            'fecha' => now()->addDays(6)->toDateString(),
            'estado' => Waitlist::ESTADO_ACTIVO,
        ]);
    }

    protected function tearDown(): void
    {
        Waitlist::query()->delete();
        Order::query()->delete();
        Payment::query()->delete();
        Appointment::withTrashed()->forceDelete();
        Service::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        \DB::connection('mongodb')->table('notifications')->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function makeUser(string $name, Role $role, string $token): User
    {
        $user = User::create(['name' => $name, 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        return $user;
    }

    /** Rutas que operan sobre los recursos del cliente A: [método, URL, cuerpo]. */
    private function targets(): array
    {
        $code = (string) $this->appointmentA->getAttribute('code');
        $appt = "/api/v1/appointments/{$code}";

        return [
            'ticket de la cita' => ['GET', "{$appt}/ticket", []],
            'reagendar la cita' => ['PUT', $appt, [
                'barber_id' => (string) $this->barberA->id,
                'service_id' => (string) $this->service->id,
                'fecha' => now()->addDays(9)->toDateString(),
                'hora_inicio' => '15:00',
            ]],
            'cambiar estado' => ['PATCH', "{$appt}/status", ['estado' => 'cancelada']],
            'borrar la cita' => ['DELETE', $appt, []],
            'agregar tiempo' => ['POST', "{$appt}/extend", ['minutos' => 10]],
            'intent de depósito' => ['POST', "{$appt}/deposit/stripe-intent", []],
            'comprobante de depósito' => ['POST', "{$appt}/deposit/receipt", []],
            'comprobante de pago' => ['POST', "{$appt}/payment/receipt", []],
            'recibo del pago' => ['GET', "/api/v1/payments/{$this->paymentA->id}/receipt", []],
            'cancelar el pedido' => ['PATCH', "/api/v1/orders/{$this->orderA->id}/cancel", []],
            'enlace de recibo del pedido' => ['GET', "/api/v1/orders/{$this->orderA->id}/receipt-link", []],
            'salir de la lista de espera' => ['DELETE', "/api/v1/waitlist/{$this->waitlistA->id}", []],
        ];
    }

    private function assertIntact(): void
    {
        $this->appointmentA->refresh();
        $this->assertSame('confirmada', (string) $this->appointmentA->estado, 'El estado de la cita cambió.');
        $this->assertSame((string) $this->barberA->id, (string) $this->appointmentA->barber_id, 'La cita se reasignó.');
        $this->assertSame('10:00:00', substr((string) $this->appointmentA->hora_inicio, 0, 8), 'La hora de la cita cambió.');
        $this->assertSame('10:30:00', substr((string) $this->appointmentA->hora_fin, 0, 8), 'Se extendió el tiempo de la cita.');
        $this->assertNotNull(Appointment::withTrashed()->find($this->appointmentA->id), 'La cita fue borrada.');
        $this->assertNull(Appointment::onlyTrashed()->find($this->appointmentA->id), 'La cita quedó en la papelera.');
        $this->assertSame('pendiente', (string) Order::find($this->orderA->id)->estado, 'El pedido cambió de estado.');
        // Salir de la lista de espera es una cancelación (estado), no un borrado: hay que mirar el estado.
        $waitlist = Waitlist::find($this->waitlistA->id);
        $this->assertNotNull($waitlist, 'La entrada de la lista de espera fue borrada.');
        $this->assertSame(Waitlist::ESTADO_ACTIVO, (string) $waitlist->estado, 'La entrada de la lista de espera fue cancelada.');
        $this->assertNotNull(Payment::find($this->paymentA->id), 'El pago fue borrado.');
    }

    private function request(string $token, string $method, string $url, array $body): TestResponse
    {
        $request = $token === '' ? $this : $this->withToken($token);

        return $request->json($method, $url, $body);
    }

    public function test_client_b_cannot_touch_client_a_resources(): void
    {
        foreach ($this->targets() as $label => [$method, $url, $body]) {
            $response = $this->request($this->tokenClientB, $method, $url, $body);

            $this->assertContains(
                $response->getStatusCode(),
                [403, 404],
                "Cliente B obtuvo {$response->getStatusCode()} en «{$label}» ({$method} {$url}); se esperaba 403 o 404.",
            );
        }

        $this->assertIntact();
    }

    public function test_barber_b_cannot_touch_resources_of_another_barbers_appointment(): void
    {
        foreach ($this->targets() as $label => [$method, $url, $body]) {
            $response = $this->request($this->tokenBarberB, $method, $url, $body);

            $this->assertContains(
                $response->getStatusCode(),
                [403, 404],
                "Barbero B obtuvo {$response->getStatusCode()} en «{$label}» ({$method} {$url}); se esperaba 403 o 404.",
            );
        }

        $this->assertIntact();
    }

    public function test_anonymous_visitor_gets_401_everywhere(): void
    {
        foreach ($this->targets() as $label => [$method, $url, $body]) {
            $response = $this->request('', $method, $url, $body);

            $this->assertSame(
                401,
                $response->getStatusCode(),
                "Sin token se obtuvo {$response->getStatusCode()} en «{$label}» ({$method} {$url}); se esperaba 401.",
            );
        }

        $this->assertIntact();
    }

    public function test_client_b_cannot_read_or_delete_client_a_notifications(): void
    {
        $notification = $this->userA->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'data' => ['message' => 'privada'],
        ]);
        $id = (string) $notification->getKey();

        $this->withToken($this->tokenClientB)->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->withToken($this->tokenClientB)->deleteJson("/api/v1/notifications/{$id}")->assertNotFound();

        $fresh = $this->userA->notifications()->where('_id', $id)->first();
        $this->assertNotNull($fresh, 'La notificación del cliente A fue borrada por el cliente B.');
        $this->assertNull($fresh->read_at, 'La notificación del cliente A fue marcada como leída por el cliente B.');
    }

    /** Control: sin esto, un 403/404 podría venir de una ruta mal escrita y no de la autorización. */
    public function test_owner_control_cases_do_work(): void
    {
        $notification = $this->userA->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'data' => ['message' => 'mía'],
        ]);
        $id = (string) $notification->getKey();

        $this->withToken($this->tokenClientA)->postJson("/api/v1/notifications/{$id}/read")->assertOk();

        $this->withToken($this->tokenClientA)
            ->deleteJson("/api/v1/waitlist/{$this->waitlistA->id}")
            ->assertSuccessful();
        $this->assertNotSame(
            Waitlist::ESTADO_ACTIVO,
            (string) Waitlist::find($this->waitlistA->id)->estado,
            'El dueño no pudo salir de su propia lista de espera.',
        );

        $this->assertNotSame(
            403,
            $this->withToken($this->tokenClientA)->getJson("/api/v1/orders/{$this->orderA->id}/receipt-link")->getStatusCode(),
            'El dueño recibió 403 en el recibo de su propio pedido.',
        );
    }
}
