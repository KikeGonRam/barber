<?php

namespace Tests\UAT;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberSchedule;
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
 * Lista de espera por HTTP (/api/v1/waitlist): quién puede anotarse, ver y cancelar. La regla de negocio (el día
 * debe estar lleno, duplicados, avisos) la cubre WaitlistServiceTest; aquí se prueba el contrato y la autorización.
 */
class WaitlistApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        // Appointment usa borrado lógico: forceDelete, o quedarían citas huérfanas para data:verify-integrity.
        Appointment::withTrashed()->forceDelete();
        foreach ([Waitlist::class, BarberSchedule::class, Barber::class, Client::class, Service::class, MobileApiToken::class] as $modelo) {
            $modelo::query()->delete();
        }
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    /** Usuario con el rol indicado y su token; los clientes llevan además perfil de cliente. */
    private function sesion(string $rol): array
    {
        $usuario = User::create(['name' => ucfirst($rol).' Espera', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $usuario->forceFill(['email_verified_at' => now(), 'role_id' => [(string) Role::where('name', $rol)->where('guard_name', 'web')->firstOrFail()->id]])->save();
        $perfil = $rol === 'cliente'
            ? Client::create(['user_id' => (string) $usuario->id, 'telefono' => '555'.random_int(1000000, 9999999), 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0])
            : null;
        $token = 'tok-'.Str::random(24);
        MobileApiToken::create(['user_id' => (string) $usuario->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        return [$token, $perfil];
    }

    /** Barbero y servicio de 8 h con un horario de 8 h: una sola cita lo deja lleno ese día (dentro de 5 días). */
    private function diaLleno(): array
    {
        $barbero = Barber::create(['nombre' => 'Barbero lleno', 'activo' => true]);
        $servicio = Service::create(['nombre' => 'Servicio largo', 'precio' => 300, 'duracion_min' => 480, 'activo' => true]);
        $fecha = now()->addDays(5);
        BarberSchedule::create(['barber_id' => (string) $barbero->id, 'day_of_week' => $fecha->dayOfWeek, 'start_time' => '09:00', 'end_time' => '17:00', 'is_working' => true]);
        $ocupante = Client::create(['telefono' => '5550009999', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        Appointment::create([
            'client_id' => (string) $ocupante->id, 'barber_id' => (string) $barbero->id, 'service_id' => (string) $servicio->id,
            'fecha' => $fecha->toDateString(), 'hora_inicio' => '09:00:00', 'hora_fin' => '17:00:00', 'estado' => 'confirmada',
        ]);

        return [$barbero, $servicio, $fecha->toDateString()];
    }

    private function cuerpo(Barber $barbero, Service $servicio, string $fecha): array
    {
        return ['barber_id' => (string) $barbero->id, 'service_id' => (string) $servicio->id, 'fecha' => $fecha];
    }

    public function test_client_joins_a_full_day_and_cannot_join_twice(): void
    {
        [$token] = $this->sesion('cliente');
        [$barbero, $servicio, $fecha] = $this->diaLleno();

        $this->withToken($token)->postJson('/api/v1/waitlist', $this->cuerpo($barbero, $servicio, $fecha))
            ->assertCreated()
            ->assertJsonPath('data.estado', Waitlist::ESTADO_ACTIVO)
            ->assertJsonPath('data.fecha', $fecha)
            ->assertJsonPath('data.service.nombre', 'Servicio largo');

        $this->withToken($token)->postJson('/api/v1/waitlist', $this->cuerpo($barbero, $servicio, $fecha))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ya estás en la lista de espera para ese barbero, servicio y fecha.');
        $this->assertSame(1, Waitlist::count());
    }

    public function test_join_is_refused_when_there_are_free_slots_or_the_date_is_past(): void
    {
        [$token] = $this->sesion('cliente');
        $barbero = Barber::create(['nombre' => 'Barbero libre', 'activo' => true]);
        $servicio = Service::create(['nombre' => 'Corte corto', 'precio' => 100, 'duracion_min' => 30, 'activo' => true]);
        $fecha = now()->addDays(5);
        BarberSchedule::create(['barber_id' => (string) $barbero->id, 'day_of_week' => $fecha->dayOfWeek, 'start_time' => '09:00', 'end_time' => '17:00', 'is_working' => true]);

        $this->withToken($token)->postJson('/api/v1/waitlist', $this->cuerpo($barbero, $servicio, $fecha->toDateString()))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Todavía hay horarios disponibles ese día -- puedes reservar directamente.');

        $this->withToken($token)->postJson('/api/v1/waitlist', $this->cuerpo($barbero, $servicio, now()->subDay()->toDateString()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');
        $this->assertSame(0, Waitlist::count());
    }

    public function test_only_clients_can_join_and_anonymous_requests_are_rejected(): void
    {
        [$recepcion] = $this->sesion('recepcionista');
        [$barbero, $servicio, $fecha] = $this->diaLleno();

        $this->withToken($recepcion)->postJson('/api/v1/waitlist', $this->cuerpo($barbero, $servicio, $fecha))->assertForbidden();
        // withToken() persiste entre peticiones del mismo test: se quita para que las siguientes sean anónimas.
        $this->withoutToken();
        $this->postJson('/api/v1/waitlist', $this->cuerpo($barbero, $servicio, $fecha))->assertUnauthorized();
        $this->getJson('/api/v1/waitlist')->assertUnauthorized();
    }

    public function test_listing_is_scoped_to_the_client_and_staff_can_filter(): void
    {
        [$tokenA, $perfilA] = $this->sesion('cliente');
        [$tokenB] = $this->sesion('cliente');
        [$recepcion] = $this->sesion('recepcionista');
        [$barbero, $servicio, $fecha] = $this->diaLleno();

        $this->withToken($tokenA)->postJson('/api/v1/waitlist', $this->cuerpo($barbero, $servicio, $fecha))->assertCreated();
        $this->withToken($tokenB)->postJson('/api/v1/waitlist', $this->cuerpo($barbero, $servicio, $fecha))->assertCreated();

        $propias = $this->withToken($tokenA)->getJson('/api/v1/waitlist')->assertOk()->json('data');
        $this->assertCount(1, $propias);
        $this->assertSame((string) $perfilA->id, $propias[0]['client']['id']);

        $this->withToken($recepcion)->getJson('/api/v1/waitlist')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($recepcion)->getJson('/api/v1/waitlist?barber_id='.$barbero->id.'&fecha='.$fecha.'&estado=activo')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($recepcion)->getJson('/api/v1/waitlist?estado=cancelado')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_owner_cancels_once_and_others_cannot_cancel(): void
    {
        [$tokenA] = $this->sesion('cliente');
        [$tokenB] = $this->sesion('cliente');
        [$recepcion] = $this->sesion('recepcionista');
        [$barbero, $servicio, $fecha] = $this->diaLleno();
        $id = $this->withToken($tokenA)->postJson('/api/v1/waitlist', $this->cuerpo($barbero, $servicio, $fecha))->json('data.id');

        $this->withToken($tokenB)->deleteJson("/api/v1/waitlist/{$id}")->assertForbidden();
        $this->withToken($recepcion)->deleteJson("/api/v1/waitlist/{$id}")->assertForbidden();

        $this->withToken($tokenA)->deleteJson("/api/v1/waitlist/{$id}")->assertOk()->assertJsonPath('message', 'Saliste de la lista de espera.');
        $this->assertSame(Waitlist::ESTADO_CANCELADO, Waitlist::query()->findOrFail($id)->getAttribute('estado'));

        $this->withToken($tokenA)->deleteJson("/api/v1/waitlist/{$id}")->assertStatus(422)->assertJsonPath('message', 'Esta anotación ya no está activa.');
    }
}
