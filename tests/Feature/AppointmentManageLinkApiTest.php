<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Service;
use App\Models\User;
use App\Services\Appointment\AppointmentManageLinkService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas.
 * Gestión de cita por el enlace del recordatorio: GET/POST
 * /api/v1/appointments/{cita}/manage*, sin sesión.
 *
 * Hasta ahora el recordatorio mandaba a /my/appointments, detrás de
 * middleware ['auth','client']: quien no recordaba su contraseña no iba, y
 * eso se traduce en no-shows. Este archivo fija que el enlace NO sea una
 * puerta más permisiva que la app: mismo estado exigido, misma ventana de
 * cancelación de la barbería, y un token que solo sirve para SU cita y deja
 * de valer cuando la cita empieza.
 */
class AppointmentManageLinkApiTest extends TestCase
{
    private AppointmentManageLinkService $links;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(BarbershopSetting::CACHE_KEY);
        BarbershopSetting::create(['nombre' => 'UrbanBlade', 'politica_cancelacion' => 24]);
        $this->links = app(AppointmentManageLinkService::class);
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        Barber::query()->delete();
        Service::query()->delete();
        User::withTrashed()->forceDelete();
        BarbershopSetting::query()->delete();
        Cache::forget(BarbershopSetting::CACHE_KEY);

        parent::tearDown();
    }

    private function appointment(int $hoursFromNow = 48, string $estado = 'confirmada'): Appointment
    {
        $barberUser = User::create(['name' => 'Barbero Enlace', 'email' => 'barbero-'.Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Enlace', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte Enlace', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        $start = now()->addHours($hoursFromNow);

        return Appointment::create([
            'client_id' => (string) Str::uuid(),
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $start->format('Y-m-d'),
            'hora_inicio' => $start->format('H:i:00'),
            'hora_fin' => $start->copy()->addMinutes(30)->format('H:i:00'),
            'estado' => $estado,
        ]);
    }

    public function test_the_link_shows_the_appointment_without_any_session(): void
    {
        $appointment = $this->appointment();
        $token = $this->links->tokenFor($appointment);

        $response = $this->getJson("/api/v1/appointments/{$appointment->code}/manage?t={$token}");

        $response->assertOk();
        $response->assertJsonPath('data.code', $appointment->code);
        $response->assertJsonPath('data.servicio', 'Corte Enlace');
        $response->assertJsonPath('data.puede_gestionar', true);
        $response->assertJsonPath('data.dentro_de_politica', true);
        $response->assertJsonPath('data.politica_horas', 24);
    }

    public function test_the_token_is_never_exposed_in_the_response(): void
    {
        $appointment = $this->appointment();
        $token = $this->links->tokenFor($appointment);

        $response = $this->getJson("/api/v1/appointments/{$appointment->code}/manage?t={$token}");

        $response->assertOk();
        // Si el token viajara de vuelta en el cuerpo, cualquier captura del
        // response (logs, proxies, capturas de pantalla) lo filtraría.
        $this->assertStringNotContainsString($token, $response->getContent());
    }

    public function test_a_wrong_or_missing_token_is_indistinguishable_from_a_missing_appointment(): void
    {
        $appointment = $this->appointment();
        $this->links->tokenFor($appointment);

        // 404, no 403: no confirma que la cita exista a quien prueba tokens.
        $this->getJson("/api/v1/appointments/{$appointment->code}/manage?t=".bin2hex(random_bytes(32)))->assertNotFound();
        $this->getJson("/api/v1/appointments/{$appointment->code}/manage")->assertNotFound();
    }

    public function test_a_token_does_not_open_someone_elses_appointment(): void
    {
        $mine = $this->appointment();
        $theirs = $this->appointment();
        $myToken = $this->links->tokenFor($mine);
        $this->links->tokenFor($theirs);

        $this->getJson("/api/v1/appointments/{$theirs->code}/manage?t={$myToken}")->assertNotFound();
    }

    public function test_the_link_stops_working_once_the_appointment_started(): void
    {
        $appointment = $this->appointment(-2);
        $token = $this->links->tokenFor($appointment);

        $this->getJson("/api/v1/appointments/{$appointment->code}/manage?t={$token}")->assertNotFound();
    }

    public function test_a_client_can_cancel_from_the_link(): void
    {
        $appointment = $this->appointment(48);
        $token = $this->links->tokenFor($appointment);

        $response = $this->postJson("/api/v1/appointments/{$appointment->code}/manage/cancel?t={$token}");

        $response->assertOk();
        // fresh(), no find(): find() devuelve Appointment|Collection y el
        // acceso a la propiedad queda sin tipar para el análisis estático.
        $fresh = $appointment->fresh();
        $this->assertSame('cancelada', $fresh->estado);
        // Libera el hueco para el índice único parcial de citas activas.
        $this->assertFalse((bool) $fresh->bloquea_horario);
    }

    public function test_the_link_respects_the_cancellation_window_of_the_barbershop(): void
    {
        // Dentro de las 24h de política: la app con sesión tampoco lo permite,
        // y el enlace no puede ser una puerta más permisiva.
        $appointment = $this->appointment(3);
        $token = $this->links->tokenFor($appointment);

        $response = $this->postJson("/api/v1/appointments/{$appointment->code}/manage/cancel?t={$token}");

        $response->assertStatus(422);
        $this->assertSame('confirmada', Appointment::find($appointment->id)->estado);
    }

    public function test_a_client_can_reschedule_from_the_link_and_it_returns_to_pending(): void
    {
        $appointment = $this->appointment(48);
        $token = $this->links->tokenFor($appointment);
        $nueva = now()->addDays(5);

        $response = $this->postJson("/api/v1/appointments/{$appointment->code}/manage/reschedule?t={$token}", [
            'fecha' => $nueva->format('Y-m-d'),
            'hora_inicio' => '16:30',
        ]);

        $response->assertOk();
        $fresh = Appointment::find($appointment->id);
        $this->assertSame($nueva->format('Y-m-d'), $fresh->fecha->format('Y-m-d'));
        $this->assertSame('16:30:00', $fresh->hora_inicio);
        // Vuelve a pendiente para que la barbería la confirme de nuevo.
        $this->assertSame('pendiente', $fresh->estado);
        // Y los recordatorios ya enviados se resetean, si no el comando la
        // saltaría para siempre en su nuevo horario.
        $this->assertNull($fresh->reminder_24h_sent_at);
    }

    public function test_rescheduling_onto_a_taken_slot_is_rejected_with_the_real_reason(): void
    {
        $appointment = $this->appointment(48);
        $token = $this->links->tokenFor($appointment);

        // Otra cita del MISMO barbero en el horario destino.
        $ocupado = now()->addDays(5);
        Appointment::create([
            'client_id' => (string) Str::uuid(),
            'barber_id' => (string) $appointment->barber_id,
            'service_id' => (string) $appointment->service_id,
            'fecha' => $ocupado->format('Y-m-d'),
            'hora_inicio' => '16:30:00',
            'hora_fin' => '17:00:00',
            'estado' => 'confirmada',
        ]);

        $response = $this->postJson("/api/v1/appointments/{$appointment->code}/manage/reschedule?t={$token}", [
            'fecha' => $ocupado->format('Y-m-d'),
            'hora_inicio' => '16:30',
        ]);

        $response->assertStatus(422);
        $this->assertSame('confirmada', Appointment::find($appointment->id)->estado);
    }

    public function test_a_cancelled_appointment_can_no_longer_be_managed(): void
    {
        $appointment = $this->appointment(48, 'cancelada');
        $token = $this->links->tokenFor($appointment);

        $this->postJson("/api/v1/appointments/{$appointment->code}/manage/cancel?t={$token}")->assertStatus(422);
        $this->postJson("/api/v1/appointments/{$appointment->code}/manage/reschedule?t={$token}", [
            'fecha' => now()->addDays(3)->format('Y-m-d'),
            'hora_inicio' => '10:00',
        ])->assertStatus(422);
    }

    public function test_the_same_appointment_always_gets_the_same_link(): void
    {
        // Dos recordatorios de la misma cita deben llevar el mismo enlace.
        $appointment = $this->appointment();

        $this->assertSame(
            $this->links->tokenFor($appointment),
            $this->links->tokenFor($appointment->fresh())
        );
    }
}
