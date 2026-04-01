<?php

namespace Tests\Feature\Acceptance;

/**
 * Cubre pruebas de aceptacion/UAT basadas en historias de usuario
 * para citas, inventario, portal cliente y portal barbero.
 */

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use App\Notifications\AppointmentNotification;

class UserStoryAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'verified',
        ]);

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
            'politica_cancelacion' => 24,
        ]);
    }

    // --- Contexto: Historia 1 (Recepcionista sin solapamientos) ---

    public function test_story1_ac1_recepcionista_can_create_when_barber_is_free(): void
    {
        [$recepcionista, $client, $barber, $service] = $this->bootstrapReceptionScenario();

        $date = now()->addDays(2)->toDateString();

        $this->actingAs($recepcionista)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '09:00',
                'hora_fin' => '09:30',
                'estado' => 'confirmada',
            ])
            ->assertRedirect(route('appointments.index'));

        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'fecha' => $date,
        ]);
    }

    public function test_story1_ac2_recepcionista_is_blocked_on_overlapping_slot(): void
    {
        [$recepcionista, $client, $barber, $service] = $this->bootstrapReceptionScenario();
        $date = now()->addDays(2)->toDateString();

        Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $this->from(route('appointments.create'))
            ->actingAs($recepcionista)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '10:15',
                'hora_fin' => '10:45',
            ])
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('hora_inicio');

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_story1_ac3_recepcionista_can_see_daily_appointments_on_index(): void
    {
        [$recepcionista, $client, $barber, $service] = $this->bootstrapReceptionScenario();
        $date = now()->addDays(2)->toDateString();

        Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '11:00:00',
            'hora_fin' => '11:30:00',
            'estado' => 'confirmada',
        ]);

        $this->actingAs($recepcionista)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee((string) $client->user->name)
            ->assertSee((string) $service->nombre);
    }

    // --- Contexto: Historia 2 (Administrador inventario) ---

    public function test_story2_ac1_admin_can_create_product_with_initial_stock(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $this->actingAs($admin)
            ->post(route('inventory.products.store'), [
                'nombre' => 'Producto UAT',
                'categoria' => 'insumos',
                'descripcion' => 'Prueba UAT',
                'precio_compra' => 50,
                'precio_venta' => 120,
                'stock_actual' => 9,
                'stock_minimo' => 3,
                'tipo' => 'insumo_trabajo',
            ])
            ->assertRedirect(route('inventory.products.index'));

        $this->assertDatabaseHas('products', [
            'nombre' => 'Producto UAT',
            'stock_actual' => 9,
        ]);
    }

    public function test_story2_ac2_admin_gets_visual_alert_for_low_stock_products(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        Product::factory()->create([
            'nombre' => 'Producto Bajo Stock',
            'stock_actual' => 2,
            'stock_minimo' => 5,
            'tipo' => 'insumo_trabajo',
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertSee('Alerta de Stock')
            ->assertSee('Producto Bajo Stock');
    }

    public function test_story2_ac3_admin_can_view_product_movement_history(): void
    {
        $admin = $this->createVerifiedUserWithRole('administrador');

        $product = Product::factory()->create([
            'nombre' => 'Producto Historial',
            'stock_actual' => 10,
            'tipo' => 'insumo_trabajo',
        ]);

        $this->actingAs($admin)
            ->post(route('inventory.movements.store'), [
                'product_id' => $product->id,
                'tipo' => 'entrada',
                'cantidad' => 4,
                'motivo' => 'Reposicion UAT',
            ])
            ->assertRedirect(route('inventory.movements.index'));

        $this->actingAs($admin)
            ->get(route('inventory.movements.index'))
            ->assertOk()
            ->assertSee('Producto Historial')
            ->assertSee('entrada');
    }

    // --- Contexto: Historia 3 (Cliente y sus citas) ---

    public function test_story3_ac1_cliente_only_sees_own_appointments(): void
    {
        $clientAUser = $this->createVerifiedClientUser('cliente.story3.a@example.com');
        $clientBUser = $this->createVerifiedClientUser('cliente.story3.b@example.com');

        $barber = Barber::factory()->create(['activo' => true]);
        $service = Service::factory()->create(['activo' => true]);
        $date = now()->addDays(2)->toDateString();

        Appointment::query()->create([
            'client_id' => $clientAUser->clientProfile->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'confirmada',
        ]);

        Appointment::query()->create([
            'client_id' => $clientBUser->clientProfile->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $this->actingAs($clientAUser)
            ->get(route('client.appointments.index'))
            ->assertOk()
            ->assertSee('09:00')
            ->assertDontSee('10:00');
    }

    public function test_story3_ac2_cliente_can_create_appointment_from_portal(): void
    {
        $clientUser = $this->createVerifiedClientUser('cliente.story3.create@example.com');
        $barber = Barber::factory()->create(['activo' => true]);
        $service = Service::factory()->create(['activo' => true, 'duracion_min' => 30]);

        $this->actingAs($clientUser)
            ->post(route('client.appointments.store'), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDays(2)->toDateString(),
                'hora_inicio' => '12:00',
            ])
            ->assertRedirect(route('client.appointments.index'));

        $this->assertDatabaseHas('appointments', [
            'client_id' => $clientUser->clientProfile->id,
            'barber_id' => $barber->id,
            'estado' => 'pendiente',
        ]);
    }

    public function test_story3_ac3_cliente_gets_confirmation_notification_after_creation(): void
    {
        Notification::fake();

        $clientUser = $this->createVerifiedClientUser('cliente.story3.notify@example.com');
        $barber = Barber::factory()->create(['activo' => true]);
        $service = Service::factory()->create(['activo' => true, 'duracion_min' => 30]);

        $this->actingAs($clientUser)
            ->post(route('client.appointments.store'), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDays(2)->toDateString(),
                'hora_inicio' => '13:00',
            ])
            ->assertRedirect(route('client.appointments.index'));

        Notification::assertSentTo($clientUser, AppointmentNotification::class);
    }

    // --- Contexto: Historia 4 (Barbero y agenda/portafolio) ---

    public function test_story4_ac1_barbero_can_only_change_status_of_own_appointments(): void
    {
        $barberUser = $this->createVerifiedUserWithRole('barbero', 'barbero.story4.owner@example.com');
        $barber = Barber::factory()->create(['user_id' => $barberUser->id, 'activo' => true]);

        $otherBarber = Barber::factory()->create(['activo' => true]);
        $client = Client::factory()->create();
        $service = Service::factory()->create(['activo' => true]);

        $ownAppointment = Appointment::factory()->create([
            'barber_id' => $barber->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'estado' => 'confirmada',
        ]);

        $this->actingAs($barberUser)
            ->patch(route('barber.appointments.status', $ownAppointment), [
                'estado' => 'en_proceso',
            ])
            ->assertRedirect();

        $this->assertSame('en_proceso', $ownAppointment->fresh()->estado);

        $otherAppointment = Appointment::factory()->create([
            'barber_id' => $otherBarber->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'estado' => 'confirmada',
        ]);

        $this->actingAs($barberUser)
            ->patch(route('barber.appointments.status', $otherAppointment), [
                'estado' => 'en_proceso',
            ])
            ->assertForbidden();
    }

    public function test_story4_ac2_modifying_other_barber_appointment_returns_403(): void
    {
        $barberUserA = $this->createVerifiedUserWithRole('barbero', 'barbero.story4.a@example.com');
        $barberUserB = $this->createVerifiedUserWithRole('barbero', 'barbero.story4.b@example.com');

        $barberA = Barber::factory()->create(['user_id' => $barberUserA->id, 'activo' => true]);
        $barberB = Barber::factory()->create(['user_id' => $barberUserB->id, 'activo' => true]);

        $appointment = Appointment::factory()->create([
            'barber_id' => $barberB->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'estado' => 'confirmada',
        ]);

        $this->actingAs($barberUserA)
            ->patch(route('barber.appointments.status', $appointment), [
                'estado' => 'completada',
            ])
            ->assertForbidden();

        $this->assertSame('confirmada', $appointment->fresh()->estado);
        $this->assertNotSame($barberA->id, $appointment->barber_id);
    }

    public function test_story4_ac3_barbero_can_upload_work_with_multiple_images(): void
    {
        Storage::fake('public');

        $barberUser = $this->createVerifiedUserWithRole('barbero', 'barbero.story4.images@example.com');

        $this->actingAs($barberUser)
            ->post(route('barbers.works.store', $barberUser), [
                'title' => 'Trabajo UAT',
                'description' => 'Prueba multi imagen',
                'images' => [
                    UploadedFile::fake()->image('uno.jpg'),
                    UploadedFile::fake()->image('dos.jpg'),
                    UploadedFile::fake()->image('tres.jpg'),
                ],
            ])
            ->assertRedirect(route('barbers.show', $barberUser));

        $work = \App\Models\Work::query()->where('barbero_id', $barberUser->id)->firstOrFail();

        $this->assertCount(3, $work->images);
        foreach ($work->images as $img) {
            Storage::disk('public')->assertExists($img->image);
        }
    }

    private function bootstrapReceptionScenario(): array
    {
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        $barber = Barber::factory()->create(['activo' => true]);
        $client = Client::factory()->create();
        $service = Service::factory()->create(['activo' => true, 'duracion_min' => 30]);

        return [$recepcionista, $client, $barber, $service];
    }

    private function createVerifiedUserWithRole(string $roleName, ?string $email = null): User
    {
        $user = User::factory()->create([
            'email' => $email ?? ($roleName . '.' . uniqid() . '@example.com'),
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createVerifiedClientUser(string $email): User
    {
        $user = $this->createVerifiedUserWithRole('cliente', $email);

        Client::query()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'preferencias_notificacion' => [
                'in_app' => true,
                'email' => true,
                'sms' => false,
                'whatsapp' => false,
            ],
        ]);

        return $user;
    }
}
