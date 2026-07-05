<?php

namespace Tests\Feature\Payments;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshMongoDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    public function test_admin_can_view_payments_index(): void
    {
        Payment::factory()->count(2)->create();

        $this->actingAs($this->admin())->get('/payments')->assertOk();
    }

    public function test_admin_can_view_create_payment_form(): void
    {
        $this->actingAs($this->admin())->get('/payments/create')->assertOk();
    }

    public function test_admin_can_register_a_payment_for_an_appointment(): void
    {
        $appointment = Appointment::factory()->create(['estado' => 'completada']);

        $response = $this->actingAs($this->admin())->post('/payments', [
            'appointment_id' => (string) $appointment->id,
            'monto' => 350,
            'metodo_pago' => 'efectivo',
            'propina' => 50,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'appointment_id' => (string) $appointment->id,
            'metodo_pago' => 'efectivo',
        ]);
    }

    public function test_store_rejects_invalid_payment_method(): void
    {
        $appointment = Appointment::factory()->create();

        $response = $this->actingAs($this->admin())->post('/payments', [
            'appointment_id' => (string) $appointment->id,
            'monto' => 350,
            'metodo_pago' => 'bitcoin',
        ]);

        $response->assertSessionHasErrors('metodo_pago');
    }

    public function test_admin_can_delete_a_payment(): void
    {
        $payment = Payment::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('payments.destroy', $payment));

        $response->assertRedirect();
        $this->assertDatabaseMissing('payments', ['_id' => $payment->id]);
    }

    public function test_barbero_cannot_manage_payments(): void
    {
        $barbero = User::factory()->create();
        $barbero->assignRole('barbero');

        $this->actingAs($barbero)->get('/payments')->assertForbidden();
    }

    public function test_recepcionista_can_manage_payments(): void
    {
        $recepcionista = User::factory()->create();
        $recepcionista->assignRole('recepcionista');

        $this->actingAs($recepcionista)->get('/payments')->assertOk();
    }

    public function test_guest_is_redirected_from_payments(): void
    {
        $this->get('/payments')->assertRedirect('/login');
    }
}
