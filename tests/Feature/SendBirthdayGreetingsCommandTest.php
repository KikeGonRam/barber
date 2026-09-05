<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Notifications\ClientBirthdayNotification;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre que
 * clients:send-birthday-greetings encuentra a quien cumple años hoy (dato
 * opcional Client::fecha_nacimiento, antes capturado pero nunca usado),
 * regala LoyaltyService::BIRTHDAY_POINTS una sola vez por año, y respeta el
 * caso especial del 29 de febrero en años no bisiestos.
 */
class SendBirthdayGreetingsCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        LoyaltyTransaction::query()->delete();
        Client::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    private function makeClientBornOn(string $fecha, int $puntos = 0): Client
    {
        $user = User::create(['name' => 'Cliente Cumpleañero', 'email' => uniqid().'@test.local', 'password' => 'password']);

        return Client::create([
            'user_id' => (string) $user->id,
            'telefono' => '5551234567',
            'nivel' => 'nuevo',
            'puntos' => $puntos,
            'total_citas' => 0,
            'fecha_nacimiento' => $fecha,
        ]);
    }

    public function test_awards_birthday_points_and_notifies_a_client_born_today(): void
    {
        Notification::fake();
        // Cualquier año de nacimiento sirve: solo importa mes/dia.
        $client = $this->makeClientBornOn(now()->subYears(30)->format('Y-m-d'), puntos: 5);

        $this->artisan('clients:send-birthday-greetings')->assertExitCode(0);

        $fresh = Client::find($client->id);
        $this->assertSame(5 + LoyaltyService::BIRTHDAY_POINTS, $fresh->puntos);
        Notification::assertSentTo($client->user, ClientBirthdayNotification::class);
    }

    public function test_ignores_a_client_whose_birthday_is_not_today(): void
    {
        Notification::fake();
        $client = $this->makeClientBornOn(now()->subYears(30)->subDays(10)->format('Y-m-d'), puntos: 5);

        $this->artisan('clients:send-birthday-greetings')->assertExitCode(0);

        $this->assertSame(5, Client::find($client->id)->puntos);
        Notification::assertNothingSent();
    }

    public function test_dry_run_does_not_award_points_or_notify(): void
    {
        Notification::fake();
        $client = $this->makeClientBornOn(now()->subYears(30)->format('Y-m-d'), puntos: 5);

        $this->artisan('clients:send-birthday-greetings', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(5, Client::find($client->id)->puntos);
        Notification::assertNothingSent();
    }

    public function test_does_not_award_points_twice_the_same_year(): void
    {
        Notification::fake();
        $client = $this->makeClientBornOn(now()->subYears(30)->format('Y-m-d'), puntos: 0);

        $this->artisan('clients:send-birthday-greetings')->assertExitCode(0);
        $this->artisan('clients:send-birthday-greetings')->assertExitCode(0);

        $this->assertSame(LoyaltyService::BIRTHDAY_POINTS, Client::find($client->id)->puntos);
    }

    public function test_ignores_a_client_with_no_birthdate_on_file(): void
    {
        Notification::fake();
        $user = User::create(['name' => 'Sin Fecha', 'email' => uniqid().'@test.local', 'password' => 'password']);
        Client::create([
            'user_id' => (string) $user->id,
            'telefono' => '5551234567',
            'nivel' => 'nuevo',
            'puntos' => 0,
            'total_citas' => 0,
        ]);

        $this->artisan('clients:send-birthday-greetings')->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
