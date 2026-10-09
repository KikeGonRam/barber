<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Tests\TestCase;

/**
 * data:verify-integrity: solo lectura; cuenta referencias rotas y falla únicamente si se pasa del umbral.
 */
class VerifyDataIntegrityCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Client::query()->delete();
    }

    protected function tearDown(): void
    {
        Client::query()->delete();
        User::withTrashed()->forceDelete();

        parent::tearDown();
    }

    public function test_reference_to_existing_user_is_not_an_orphan(): void
    {
        $user = User::create(['name' => 'Cliente Real', 'email' => 'ok-'.uniqid().'@test.local', 'password' => 'password']);
        Client::create(['user_id' => (string) $user->id, 'telefono' => '5550001111', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $this->artisan('data:verify-integrity')
            ->expectsOutputToContain('Huérfanos en total: 0')
            ->assertExitCode(0);
    }

    public function test_broken_reference_fails_and_is_not_modified(): void
    {
        $client = Client::create(['user_id' => '000000000000000000000000', 'telefono' => '5550002222', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $this->artisan('data:verify-integrity')
            ->expectsOutputToContain('Huérfanos en total: 1')
            ->assertExitCode(1);

        $this->assertNotNull(Client::find($client->id), 'El comando es de solo lectura: no debe borrar nada.');
    }

    public function test_threshold_tolerates_known_orphans(): void
    {
        Client::create(['user_id' => '000000000000000000000000', 'telefono' => '5550003333', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $this->artisan('data:verify-integrity', ['--max-orphans' => 1])->assertExitCode(0);
    }
}
