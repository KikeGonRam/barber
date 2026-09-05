<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\RaffleResult;
use App\Models\User;
use App\Notifications\RaffleWinNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre que el sorteo
 * mensual fija una fecha de vencimiento al premio (antes no existía ninguna)
 * y notifica con RaffleWinNotification en vez de reutilizar el aviso de
 * "subiste de nivel" (LoyaltyNotification con discount:100, que le decía al
 * ganador que tenía descuento permanente).
 */
class DrawMonthlyRaffleTest extends TestCase
{
    protected function tearDown(): void
    {
        RaffleResult::query()->delete();
        Client::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    private function makeEligibleClient(string $nivel = 'vip'): Client
    {
        $user = User::create(['name' => 'Cliente Elegible', 'email' => uniqid().'@test.local', 'password' => 'password']);

        return Client::create(['user_id' => (string) $user->id, 'telefono' => '5551234567', 'nivel' => $nivel, 'puntos' => 0, 'total_citas' => 15]);
    }

    public function test_sets_an_expiration_date_and_notifies_with_the_dedicated_raffle_notification(): void
    {
        Notification::fake();
        $client = $this->makeEligibleClient();

        $this->artisan('loyalty:draw-raffle', ['--month' => '2026-01'])->assertExitCode(0);

        $result = RaffleResult::where('mes', '2026-01')->first();

        $this->assertNotNull($result);
        $this->assertNotNull($result->vence_en);
        $this->assertEqualsWithDelta(
            RaffleResult::VIGENCIA_DIAS,
            (int) $result->created_at->diffInDays($result->vence_en),
            1
        );

        Notification::assertSentTo($client->user, RaffleWinNotification::class);
    }

    public function test_does_not_draw_twice_for_the_same_month(): void
    {
        $this->makeEligibleClient();

        $this->artisan('loyalty:draw-raffle', ['--month' => '2026-02'])->assertExitCode(0);
        $this->artisan('loyalty:draw-raffle', ['--month' => '2026-02'])->assertExitCode(1);

        $this->assertSame(1, RaffleResult::where('mes', '2026-02')->count());
    }

    public function test_fails_when_no_eligible_clients_exist(): void
    {
        $this->artisan('loyalty:draw-raffle', ['--month' => '2026-03'])->assertExitCode(1);

        $this->assertSame(0, RaffleResult::where('mes', '2026-03')->count());
    }
}
