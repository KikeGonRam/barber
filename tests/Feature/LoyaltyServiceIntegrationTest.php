<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LoyaltyTransaction;
use App\Services\Loyalty\LoyaltyService;
use Tests\TestCase;

/**
 * Prueba de integración real contra Mongo (contenedor "mongo-test" local,
 * ver .env.testing y docker-compose.yml). A propósito NO usa mocks: el
 * objetivo es verificar que el wiring app <-> Mongo local funciona, y que
 * el aislamiento de la base de datos ("barber_db_test") es real, nunca la
 * Atlas compartida con spark/.
 */
class LoyaltyServiceIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        LoyaltyTransaction::query()->where('referencia_id', 'cita-integration-test')->delete();
        Client::query()->where('telefono', '0000000000')->delete();

        parent::tearDown();
    }

    public function test_runs_against_the_isolated_local_test_database(): void
    {
        $this->assertSame('barber_db_test', config('database.connections.mongodb.database'));
    }

    public function test_award_cita_points_persists_puntos_total_citas_and_transaction(): void
    {
        $client = Client::create([
            'telefono' => '0000000000',
            'nivel' => 'nuevo',
            'puntos' => 0,
            'total_citas' => 0,
        ]);

        (new LoyaltyService)->awardCitaPoints($client, 'cita-integration-test');

        $client->refresh();

        $this->assertSame(10, $client->puntos);
        $this->assertSame(1, $client->total_citas);
        $this->assertSame('nuevo', $client->nivel);

        $transaction = LoyaltyTransaction::query()->where('referencia_id', 'cita-integration-test')->first();

        $this->assertNotNull($transaction);
        $this->assertSame((string) $client->id, $transaction->client_id);
        $this->assertSame('ganado', $transaction->tipo);
        $this->assertSame(10, $transaction->puntos);
    }

    public function test_redeem_points_fails_when_client_has_insufficient_balance(): void
    {
        $client = Client::create([
            'telefono' => '0000000000',
            'nivel' => 'nuevo',
            'puntos' => 3,
            'total_citas' => 0,
        ]);

        $result = (new LoyaltyService)->redeemPoints($client, 10, 'Canje de prueba');

        $client->refresh();

        $this->assertFalse($result);
        $this->assertSame(3, $client->puntos);
    }
}
