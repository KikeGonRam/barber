<?php

namespace Tests\Feature;

use App\Exceptions\Domain\GiftCardException;
use App\Exceptions\Domain\PaymentException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\GiftCard;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\Package\GiftCardService;
use App\Services\Payment\CashCloseService;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tarjetas de regalo (roadmap P1, ver GiftCardService). Cubre la compra, el
 * canje parcial y total contra un cobro real, la exclusión mutua con
 * premio de rifa/paquete, y su inclusión en el corte de caja.
 */
class GiftCardServiceTest extends TestCase
{
    private GiftCardService $giftCards;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->giftCards = app(GiftCardService::class);
        $this->payments = app(PaymentService::class);
    }

    protected function tearDown(): void
    {
        GiftCard::query()->delete();
        Payment::query()->delete();
        Appointment::withTrashed()->forceDelete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_purchase_cash_creates_an_active_gift_card_with_full_balance(): void
    {
        $giftCard = $this->giftCards->purchaseCash(500, null, 'Juan Pérez', null, (string) Str::uuid());

        $this->assertEquals(500.0, (float) $giftCard->saldo);
        $this->assertEquals(500.0, (float) $giftCard->monto_inicial);
        $this->assertSame(GiftCard::ESTADO_ACTIVA, $giftCard->estado);
        $this->assertNotEmpty($giftCard->code);
    }

    public function test_purchase_rejects_amount_outside_allowed_range(): void
    {
        $this->expectException(GiftCardException::class);
        $this->giftCards->purchaseCash(10, null, 'Alguien', null, (string) Str::uuid());
    }

    public function test_find_redeemable_returns_null_for_unknown_code(): void
    {
        $this->assertNull($this->giftCards->findRedeemable('no-existe'));
    }

    public function test_apply_partially_covers_a_charge_and_keeps_remaining_balance(): void
    {
        $giftCard = $this->giftCards->purchaseCash(100, null, 'Regalo', null, (string) Str::uuid());

        $aplicado = $this->giftCards->apply($giftCard, 250);

        $this->assertEquals(100.0, $aplicado);
        $fresh = GiftCard::find($giftCard->id);
        $this->assertEquals(0.0, (float) $fresh->saldo);
        $this->assertSame(GiftCard::ESTADO_AGOTADA, $fresh->estado);
    }

    public function test_apply_covers_less_than_the_full_balance_when_charge_is_smaller(): void
    {
        $giftCard = $this->giftCards->purchaseCash(500, null, 'Regalo', null, (string) Str::uuid());

        $aplicado = $this->giftCards->apply($giftCard, 150);

        $this->assertEquals(150.0, $aplicado);
        $fresh = GiftCard::find($giftCard->id);
        $this->assertEquals(350.0, (float) $fresh->saldo);
        $this->assertSame(GiftCard::ESTADO_ACTIVA, $fresh->estado);
    }

    public function test_apply_throws_when_balance_already_exhausted(): void
    {
        $giftCard = $this->giftCards->purchaseCash(100, null, 'Regalo', null, (string) Str::uuid());
        $this->giftCards->apply($giftCard, 100);

        $this->expectException(GiftCardException::class);
        $this->giftCards->apply($giftCard->fresh(), 50);
    }

    private function makeAppointment(): array
    {
        $client = Client::create(['telefono' => '5551110000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 300, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        return compact('client', 'barber', 'service', 'appointment');
    }

    public function test_payment_service_applies_gift_card_and_charges_the_remainder(): void
    {
        Notification::fake();

        ['appointment' => $appointment] = $this->makeAppointment(); // servicio $300
        $giftCard = $this->giftCards->purchaseCash(100, null, 'Regalo', null, (string) Str::uuid());

        $payment = $this->payments->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 300,
            'metodo_pago' => 'efectivo',
            'codigo_gift_card' => $giftCard->code,
        ], (string) Str::uuid());

        // 300 (precio) - 100 (gift card) = 200 que sí se cobra.
        $this->assertEquals(200.0, (float) $payment->monto);
        $this->assertSame((string) $giftCard->id, $payment->gift_card_id);
        $this->assertEquals(100.0, (float) $payment->gift_card_monto_aplicado);
        $this->assertSame(GiftCard::ESTADO_AGOTADA, GiftCard::find($giftCard->id)->estado);
    }

    public function test_payment_service_rejects_an_invalid_gift_card_code(): void
    {
        Notification::fake();

        ['appointment' => $appointment] = $this->makeAppointment();

        $this->expectException(PaymentException::class);
        $this->payments->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 300,
            'metodo_pago' => 'efectivo',
            'codigo_gift_card' => 'codigo-invalido',
        ], (string) Str::uuid());
    }

    public function test_payment_service_rejects_combining_gift_card_with_raffle_prize(): void
    {
        Notification::fake();

        ['appointment' => $appointment] = $this->makeAppointment();
        $giftCard = $this->giftCards->purchaseCash(100, null, 'Regalo', null, (string) Str::uuid());

        $this->expectException(PaymentException::class);
        $this->payments->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 0,
            'metodo_pago' => 'efectivo',
            'codigo_gift_card' => $giftCard->code,
            'usar_premio_rifa' => true,
        ], (string) Str::uuid());
    }

    public function test_cash_close_includes_gift_card_purchases_in_the_daily_total(): void
    {
        $this->giftCards->purchaseCash(500, null, 'Regalo', null, (string) Str::uuid());

        $cashClose = app(CashCloseService::class);
        $expected = $cashClose->expectedFor(Carbon::today());

        $this->assertSame(1, $expected['gift_cards']);
        $this->assertEquals(500.0, $expected['por_metodo']['efectivo']);
    }

    public function test_mine_lists_only_the_authenticated_clients_own_gift_cards_newest_first(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $ownUser = User::create(['name' => 'Cliente Gift Card', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $ownUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $owner = Client::create(['user_id' => (string) $ownUser->id, 'telefono' => '5551230000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $otherUser = User::create(['name' => 'Otro Cliente', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $other = Client::create(['user_id' => (string) $otherUser->id, 'telefono' => '5559990000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $older = $this->giftCards->purchaseCash(100, $owner, null, null, (string) Str::uuid());
        $older->update(['comprado_en' => now()->subDay()]);
        $newer = $this->giftCards->purchaseCash(200, $owner, null, null, (string) Str::uuid());
        $this->giftCards->purchaseCash(300, $other, null, null, (string) Str::uuid());

        $token = 'test-plaintext-token-gift-card-mine';
        MobileApiToken::create(['user_id' => (string) $ownUser->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/gift-cards/mine');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.code', $newer->code);
        $response->assertJsonPath('data.1.code', $older->code);
    }
}
