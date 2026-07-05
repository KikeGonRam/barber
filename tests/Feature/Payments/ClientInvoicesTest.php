<?php

namespace Tests\Feature\Payments;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a production 500: seeders that bulk-insert Payment/
 * LoyaltyTransaction documents via Payment::raw()->insertMany() must convert
 * dates to MongoDB\BSON\UTCDateTime before inserting. Passing a raw Carbon
 * instance gets serialized as an empty sub-document ({}), and every
 * `$payment->created_at?->format(...)` in the invoices view then throws
 * "preg_match(): Argument #2 ($subject) must be of type string, array given".
 */
class ClientInvoicesTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_client_can_view_their_invoices_with_a_valid_payment_date(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cliente');
        $client = Client::factory()->create(['user_id' => $user->id]);

        $appointment = Appointment::factory()->create([
            'client_id' => (string) $client->id,
            'estado' => 'completada',
        ]);

        Payment::factory()->create([
            'appointment_id' => (string) $appointment->id,
        ]);

        $response = $this->actingAs($user)->get(route('client.facturas.index'));

        $response->assertOk();
    }

    public function test_guest_is_redirected_from_client_invoices(): void
    {
        $this->get(route('client.facturas.index'))->assertRedirect('/login');
    }

    public function test_payment_seeder_produces_a_formattable_created_at(): void
    {
        Appointment::factory()->create(['estado' => 'completada']);

        $this->seed(\Database\Seeders\PaymentSeeder::class);

        $payment = Payment::first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->created_at);
        $this->assertIsString($payment->created_at->format('Y-m-d H:i'));
    }
}
