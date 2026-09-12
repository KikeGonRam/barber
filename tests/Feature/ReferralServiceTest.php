<?php

namespace Tests\Feature;

use App\Exceptions\Domain\ReferralException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\Service;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Loyalty\ReferralService;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Programa de referidos (roadmap P1, ver ReferralService). Cubre el
 * código único auto-generado, el guard de auto-referirse y de referirse
 * dos veces, y que la recompensa solo llegue en la PRIMERA cita completada
 * del referido -- nunca por solo registrarse ni en citas posteriores.
 */
class ReferralServiceTest extends TestCase
{
    private ReferralService $referrals;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referrals = app(ReferralService::class);
    }

    protected function tearDown(): void
    {
        Payment::query()->delete();
        Referral::query()->delete();
        Appointment::withTrashed()->forceDelete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();

        parent::tearDown();
    }

    private function makeClient(): Client
    {
        return Client::create(['telefono' => (string) random_int(5550000000, 5559999999), 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
    }

    public function test_every_client_gets_a_unique_referral_code_on_creation(): void
    {
        $client = $this->makeClient();

        $this->assertNotEmpty($client->codigo_referido);
        $this->assertSame(6, strlen($client->codigo_referido));
    }

    public function test_link_creates_a_pending_referral(): void
    {
        $referrer = $this->makeClient();
        $referee = $this->makeClient();

        $referral = $this->referrals->link($referee, $referrer->codigo_referido);

        $this->assertSame((string) $referrer->id, $referral->referrer_client_id);
        $this->assertSame((string) $referee->id, $referral->referee_client_id);
        $this->assertSame(Referral::ESTADO_PENDIENTE, $referral->estado);
    }

    public function test_link_is_case_insensitive_on_the_code(): void
    {
        $referrer = $this->makeClient();
        $referee = $this->makeClient();

        $referral = $this->referrals->link($referee, mb_strtolower($referrer->codigo_referido));

        $this->assertSame((string) $referrer->id, $referral->referrer_client_id);
    }

    public function test_link_rejects_an_unknown_code(): void
    {
        $referee = $this->makeClient();

        $this->expectException(ReferralException::class);
        $this->referrals->link($referee, 'NOEXISTE');
    }

    public function test_link_rejects_self_referral(): void
    {
        $client = $this->makeClient();

        $this->expectException(ReferralException::class);
        $this->referrals->link($client, $client->codigo_referido);
    }

    public function test_link_rejects_a_second_referral_for_the_same_referee(): void
    {
        $referrerA = $this->makeClient();
        $referrerB = $this->makeClient();
        $referee = $this->makeClient();

        $this->referrals->link($referee, $referrerA->codigo_referido);

        $this->expectException(ReferralException::class);
        $this->referrals->link($referee, $referrerB->codigo_referido);
    }

    private function makeCompletedAppointment(Client $client): Appointment
    {
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->format('Y-m-d'),
            'hora_inicio' => sprintf('%02d:00:00', random_int(8, 17)),
            'hora_fin' => sprintf('%02d:30:00', random_int(8, 17)),
            'estado' => 'completada',
        ]);
    }

    public function test_complete_if_eligible_awards_points_to_the_referrer_on_first_completed_appointment(): void
    {
        $referrer = $this->makeClient();
        $referee = $this->makeClient();
        $this->referrals->link($referee, $referrer->codigo_referido);

        $this->makeCompletedAppointment($referee);
        $this->referrals->completeIfEligible($referee->fresh());

        $this->assertEquals(LoyaltyService::REFERRAL_POINTS, Client::find($referrer->id)->puntos);
        $referral = Referral::where('referee_client_id', (string) $referee->id)->first();
        $this->assertSame(Referral::ESTADO_COMPLETADO, $referral->estado);
        $this->assertNotNull($referral->recompensa_otorgada_en);
    }

    public function test_complete_if_eligible_does_nothing_without_a_pending_referral(): void
    {
        $referee = $this->makeClient();
        $this->makeCompletedAppointment($referee);

        // No debe lanzar ni hacer nada -- no hay referral que buscar.
        $this->referrals->completeIfEligible($referee->fresh());

        $this->assertSame(0, Referral::count());
    }

    public function test_complete_if_eligible_does_not_double_award_on_a_second_completed_appointment(): void
    {
        $referrer = $this->makeClient();
        $referee = $this->makeClient();
        $this->referrals->link($referee, $referrer->codigo_referido);

        $this->makeCompletedAppointment($referee);
        $this->referrals->completeIfEligible($referee->fresh());

        // Segunda cita completada -- ya no debe volver a otorgar puntos.
        $this->makeCompletedAppointment($referee);
        $this->referrals->completeIfEligible($referee->fresh());

        $this->assertEquals(LoyaltyService::REFERRAL_POINTS, Client::find($referrer->id)->puntos);
    }

    public function test_payment_service_triggers_referral_reward_on_completing_the_appointment(): void
    {
        Notification::fake();

        $referrer = $this->makeClient();
        $referee = $this->makeClient();
        $this->referrals->link($referee, $referrer->codigo_referido);

        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'client_id' => (string) $referee->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        app(PaymentService::class)->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 200,
            'metodo_pago' => 'efectivo',
        ], (string) Str::uuid());

        $this->assertEquals(LoyaltyService::REFERRAL_POINTS, Client::find($referrer->id)->puntos);
    }
}
