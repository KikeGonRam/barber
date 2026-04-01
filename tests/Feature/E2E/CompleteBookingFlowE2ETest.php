<?php

namespace Tests\Feature\E2E;

/**
 * Cubre flujo E2E de reserva completa: registro de cliente, agenda,
 * confirmacion, atencion por barbero, pago y cierre; incluye flujo de
 * cancelacion con liberacion de slot para nueva cita.
 */

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompleteBookingFlowE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
            'verified',
        ]);

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
            'politica_cancelacion' => 24,
        ]);
    }

    // --- Contexto: Flujo completo (happy path) ---

    public function test_complete_booking_happy_path_from_register_to_payment_completion(): void
    {
        Storage::fake('public');

        $this->createVerifiedUserWithRole('administrador');

        $barberUser = $this->createVerifiedUserWithRole('barbero', [
            'name' => 'Barbero Elite',
            'email' => 'barbero.elite@example.com',
        ]);

        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
            'especialidades' => 'fade, clasico',
        ]);

        $service = Service::factory()->create([
            'nombre' => 'Corte Premium',
            'precio' => 350,
            'duracion_min' => 30,
            'activo' => true,
        ]);

        // 1) Usuario se registra y obtiene rol cliente + perfil
        $this->post('/register', [
            'name' => 'Cliente E2E',
            'email' => 'cliente.e2e@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('dashboard', absolute: false));

        $clientUser = User::query()->where('email', 'cliente.e2e@example.com')->firstOrFail();
        $clientUser->markEmailAsVerified();

        $this->assertTrue($clientUser->hasRole('cliente'));
        $this->assertNotNull($clientUser->clientProfile);

        // 2) Cliente ve lista de barberos disponibles
        $this->actingAs($clientUser)
            ->followingRedirects()
            ->get(route('client.appointments.create'))
            ->assertOk()
            ->assertSee('Barbero Elite')
            ->assertSee('Corte Premium');

        $date = now()->addDays(2)->toDateString();

        // 3) Cliente crea cita en estado pendiente
        $this->actingAs($clientUser)
            ->post(route('client.appointments.store'), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '11:00',
            ])
            ->assertRedirect(route('client.appointments.index'));

        $appointment = Appointment::query()
            ->where('client_id', $clientUser->clientProfile->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('pendiente', $appointment->estado);

        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        // 4) Recepcionista confirma cita
        $this->actingAs($recepcionista)
            ->put(route('appointments.update', $appointment), [
                'client_id' => $appointment->client_id,
                'barber_id' => $appointment->barber_id,
                'service_id' => $appointment->service_id,
                'fecha' => $appointment->fecha->toDateString(),
                'hora_inicio' => substr((string) $appointment->hora_inicio, 0, 5),
                'hora_fin' => substr((string) $appointment->hora_fin, 0, 5),
                'estado' => 'confirmada',
            ])
            ->assertRedirect(route('appointments.index'));

        $appointment->refresh();
        $this->assertSame('confirmada', $appointment->estado);

        // 5) Barbero cambia estado a en_proceso
        $this->actingAs($barberUser)
            ->patch(route('barber.appointments.status', $appointment), [
                'estado' => 'en_proceso',
                'notas' => 'Iniciando servicio',
            ])
            ->assertRedirect();

        $appointment->refresh();
        $this->assertSame('en_proceso', $appointment->estado);

        // 6) Recepcionista registra pago y cierre completada + PDF
        $this->actingAs($recepcionista)
            ->post(route('payments.store'), [
                'appointment_id' => $appointment->id,
                'monto' => 350,
                'metodo_pago' => 'tarjeta',
                'propina' => 50,
            ])
            ->assertRedirect(route('payments.index'));

        $appointment->refresh();
        $payment = Payment::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->assertSame('completada', $appointment->estado);
        $this->assertSame('350.00', number_format((float) $appointment->precio_cobrado, 2, '.', ''));
        $this->assertNotNull($payment->comprobante_pdf);
        Storage::disk('public')->assertExists($payment->comprobante_pdf);

        // 7) Cliente visualiza su cita como completada
        $this->actingAs($clientUser)
            ->get(route('client.appointments.index'))
            ->assertOk()
            ->assertSee('completada');
    }

    // --- Contexto: Flujo de cancelacion y liberacion de slot ---

    public function test_cancellation_releases_slot_for_new_appointment_same_barber_and_time(): void
    {
        $barberUser = $this->createVerifiedUserWithRole('barbero', [
            'name' => 'Barbero Slot',
            'email' => 'barbero.slot@example.com',
        ]);

        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
        ]);

        $service = Service::factory()->create([
            'duracion_min' => 30,
            'activo' => true,
        ]);

        $clientUserA = $this->createVerifiedClientUser('cliente.a@example.com');
        $clientUserB = $this->createVerifiedClientUser('cliente.b@example.com');
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        $date = now()->addDays(3)->toDateString();

        // Cliente A crea cita
        $this->actingAs($clientUserA)
            ->post(route('client.appointments.store'), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '12:00',
            ])
            ->assertRedirect(route('client.appointments.index'));

        $appointmentA = Appointment::query()
            ->where('client_id', $clientUserA->clientProfile->id)
            ->latest('id')
            ->firstOrFail();

        // Recepcionista cancela
        $this->actingAs($recepcionista)
            ->delete(route('appointments.destroy', $appointmentA))
            ->assertRedirect(route('appointments.index'));

        $appointmentA->refresh();
        $this->assertSame('cancelada', $appointmentA->estado);

        // Mismo slot queda libre para cliente B
        $this->actingAs($recepcionista)
            ->post(route('appointments.store'), [
                'client_id' => $clientUserB->clientProfile->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '12:00',
                'hora_fin' => '12:30',
                'estado' => 'confirmada',
            ])
            ->assertRedirect(route('appointments.index'));

        $this->assertDatabaseHas('appointments', [
            'client_id' => $clientUserB->clientProfile->id,
            'barber_id' => $barber->id,
            'fecha' => now()->addDays(3)->startOfDay()->format('Y-m-d H:i:s'),
            'hora_inicio' => '12:00',
            'hora_fin' => '12:30',
        ]);
    }

    private function createVerifiedUserWithRole(string $roleName, array $attributes = []): User
    {
        $defaults = [
            'name' => 'User '.$roleName,
            'email' => $roleName.'.'.uniqid().'@example.com',
            'email_verified_at' => now(),
        ];

        $user = User::factory()->create(array_merge($defaults, $attributes));

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createVerifiedClientUser(string $email): User
    {
        $user = $this->createVerifiedUserWithRole('cliente', [
            'name' => 'Cliente '.$email,
            'email' => $email,
        ]);

        Client::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'preferencias_notificacion' => [
                    'in_app' => true,
                    'email' => true,
                    'sms' => false,
                    'whatsapp' => false,
                ],
            ]
        );

        return $user;
    }
}
