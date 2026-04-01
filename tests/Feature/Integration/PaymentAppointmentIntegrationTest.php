<?php

namespace Tests\Feature\Integration;

/**
 * Cubre integracion end-to-end entre Controller, Service, Model y BD
 * para pagos, citas, inventario, notificaciones y auditoria.
 */

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAppointmentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
        ]);
    }

    // --- Contexto: pagos y cita ---

    public function test_registering_payment_updates_appointment_to_completed_and_sets_price(): void
    {
        Storage::fake('public');

        [$recepcionista, $appointment] = $this->bootstrapAppointmentScenario();

        $this->actingAs($recepcionista)
            ->post(route('payments.store'), [
                'appointment_id' => $appointment->id,
                'monto' => 300,
                'metodo_pago' => 'efectivo',
                'propina' => 20,
            ])
            ->assertRedirect(route('payments.index'));

        $appointment->refresh();

        $this->assertSame('completada', $appointment->estado);
        $this->assertSame('300.00', number_format((float) $appointment->precio_cobrado, 2, '.', ''));
    }

    public function test_registering_payment_generates_pdf_and_saves_in_public_storage(): void
    {
        Storage::fake('public');

        [$recepcionista, $appointment] = $this->bootstrapAppointmentScenario();

        $this->actingAs($recepcionista)
            ->post(route('payments.store'), [
                'appointment_id' => $appointment->id,
                'monto' => 280,
                'metodo_pago' => 'tarjeta',
                'propina' => 0,
            ])
            ->assertRedirect(route('payments.index'));

        $payment = Payment::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->assertNotNull($payment->comprobante_pdf);
        Storage::disk('public')->assertExists($payment->comprobante_pdf);
    }

    // --- Contexto: inventario integrado ---

    public function test_creating_output_movement_decrements_product_stock(): void
    {
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        $product = Product::factory()->create([
            'stock_actual' => 20,
            'stock_minimo' => 5,
            'tipo' => 'insumo_trabajo',
        ]);

        $this->actingAs($recepcionista)
            ->post(route('inventory.movements.store'), [
                'product_id' => $product->id,
                'tipo' => 'salida',
                'cantidad' => 4,
                'motivo' => 'Uso en servicio',
            ])
            ->assertRedirect(route('inventory.movements.index'));

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'tipo' => 'salida',
            'cantidad' => 4,
        ]);

        $this->assertSame(16, (int) $product->fresh()->stock_actual);
    }

    public function test_creating_input_movement_increments_product_stock(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $product = Product::factory()->create([
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'tipo' => 'insumo_trabajo',
        ]);

        $this->actingAs($admin)
            ->post(route('inventory.movements.store'), [
                'product_id' => $product->id,
                'tipo' => 'entrada',
                'cantidad' => 7,
                'motivo' => 'Reposicion proveedor',
            ])
            ->assertRedirect(route('inventory.movements.index'));

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'tipo' => 'entrada',
            'cantidad' => 7,
        ]);

        $this->assertSame(17, (int) $product->fresh()->stock_actual);
    }

    // --- Contexto: notificaciones ---

    public function test_creating_appointment_dispatches_notification_to_client(): void
    {
        Notification::fake();

        [$recepcionista, $client, $barber, $service] = $this->bootstrapCreationScenario();

        $this->actingAs($recepcionista)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => '10:00',
                'hora_fin' => '10:30',
                'estado' => 'confirmada',
            ])
            ->assertRedirect(route('appointments.index'));

        Notification::assertSentTo($client->user, AppointmentNotification::class);
    }

    public function test_appointments_send_reminders_command_marks_reminder_24h_sent_at(): void
    {
        Notification::fake();

        Carbon::setTestNow(Carbon::parse('2026-04-01 10:15:00'));

        try {
            [$recepcionista, $client, $barber, $service] = $this->bootstrapCreationScenario();

            $appointment = Appointment::query()->create([
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => now()->addDay()->startOfHour()->format('H:i'),
                'hora_fin' => now()->addDay()->startOfHour()->addMinutes(30)->format('H:i'),
                'estado' => 'confirmada',
            ]);

            Artisan::call('appointments:send-reminders');

            Notification::assertSentTo($client->user, AppointmentNotification::class);
            $this->assertNotNull($appointment->fresh()->reminder_24h_sent_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    // --- Contexto: auditoria ---

    public function test_creating_user_with_role_registers_activity_log_entry(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Usuario Log Integracion',
                'email' => 'usuario.log.integracion@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'cliente',
            ])
            ->assertRedirect(route('users.index'));

        $createdUser = User::query()->where('email', 'usuario.log.integracion@example.com')->firstOrFail();

        $this->assertTrue($createdUser->hasRole('cliente'));
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $createdUser->id,
            'event' => 'created',
        ]);

        $this->assertGreaterThan(0, Activity::query()->count());
    }

    private function bootstrapAppointmentScenario(): array
    {
        [$recepcionista, $client, $barber, $service] = $this->bootstrapCreationScenario();

        $appointment = Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '12:00',
            'hora_fin' => '12:30',
            'estado' => 'confirmada',
        ]);

        return [$recepcionista, $appointment];
    }

    private function bootstrapCreationScenario(): array
    {
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        $barberUser = $this->createVerifiedUserWithRole('barbero');
        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
        ]);

        $clientUser = $this->createVerifiedUserWithRole('cliente');
        $client = Client::query()->firstOrCreate(
            ['user_id' => $clientUser->id],
            [
                'preferencias_notificacion' => [
                    'in_app' => true,
                    'email' => true,
                    'sms' => false,
                    'whatsapp' => false,
                ],
            ]
        );

        $service = Service::factory()->create([
            'activo' => true,
            'duracion_min' => 30,
        ]);

        return [$recepcionista, $client, $barber, $service];
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
}
