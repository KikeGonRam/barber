<?php

namespace Tests\Feature\Payments;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_recepcionista_can_register_payment_and_generate_receipt(): void
    {
        Storage::fake('public');

        [$actor, $appointment] = $this->bootstrapScenario();

        $this->actingAs($actor)
            ->post(route('payments.store'), [
                'appointment_id' => $appointment->id,
                'monto' => 250,
                'metodo_pago' => 'efectivo',
                'propina' => 20,
            ])
            ->assertRedirect(route('payments.index'));

        $payment = Payment::query()->first();

        $this->assertNotNull($payment);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'appointment_id' => $appointment->id,
            'metodo_pago' => 'efectivo',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'estado' => 'completada',
            'precio_cobrado' => 250,
        ]);

        $this->assertNotNull($payment->comprobante_pdf);
        Storage::disk('public')->assertExists($payment->comprobante_pdf);
    }

    public function test_cannot_register_duplicate_payment_for_same_appointment(): void
    {
        Storage::fake('public');

        [$actor, $appointment] = $this->bootstrapScenario();

        Payment::query()->create([
            'appointment_id' => $appointment->id,
            'monto' => 200,
            'metodo_pago' => 'efectivo',
            'propina' => 0,
            'created_by' => $actor->id,
        ]);

        $this->from(route('payments.create'))
            ->actingAs($actor)
            ->post(route('payments.store'), [
                'appointment_id' => $appointment->id,
                'monto' => 250,
                'metodo_pago' => 'tarjeta',
                'propina' => 0,
            ])
            ->assertRedirect(route('payments.create'))
            ->assertSessionHasErrors('appointment_id');

        $this->assertSame(1, Payment::query()->count());
    }

    private function bootstrapScenario(): array
    {
        $actor = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'recepcionista',
            'guard_name' => 'web',
        ]);

        $actor->assignRole($role);

        $barberUser = User::factory()->create();
        $clientUser = User::factory()->create();

        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
        ]);

        $client = Client::query()->create([
            'user_id' => $clientUser->id,
        ]);

        $service = Service::query()->create([
            'nombre' => 'Corte clásico',
            'categoria' => 'corte',
            'precio' => 200,
            'duracion_min' => 30,
            'activo' => true,
        ]);

        $appointment = Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => '2026-03-11',
            'hora_inicio' => '12:00',
            'hora_fin' => '12:30',
            'estado' => 'confirmada',
        ]);

        return [$actor, $appointment];
    }
}
