<?php

namespace Tests\Feature;

use App\Console\Commands\CleanExpiredTokens;
use App\Models\MobileApiToken;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre las dos causas de
 * limpieza de tokens móviles: expirados por fecha (ya existía) e inactivos
 * por 90+ días aunque todavía no expiren por fecha (nuevo — antes un token
 * de un celular perdido/reinstalado seguía siendo válido hasta sus 6 meses
 * naturales sin importar cuánto llevara sin usarse).
 */
class CleanExpiredTokensCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create(['name' => 'Usuario Token', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
    }

    private function makeToken(User $user, array $overrides = []): MobileApiToken
    {
        $token = MobileApiToken::create(array_merge([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', Str::uuid()->toString()),
        ], $overrides));

        // created_at/last_used_at no son mass-assignable en algunos casos via
        // create(); se fuerzan aparte para simular tokens viejos.
        if (isset($overrides['created_at']) || isset($overrides['last_used_at'])) {
            $token->forceFill(array_intersect_key($overrides, array_flip(['created_at', 'last_used_at'])))->save();
        }

        return $token;
    }

    public function test_deletes_a_token_past_its_expires_at(): void
    {
        $user = $this->makeUser();
        $expired = $this->makeToken($user, ['expires_at' => now()->subDay()]);
        $valid = $this->makeToken($user, ['expires_at' => now()->addDays(30), 'last_used_at' => now()]);

        $this->artisan('tokens:clean-expired')->assertExitCode(0);

        $this->assertNull(MobileApiToken::find($expired->id));
        $this->assertNotNull(MobileApiToken::find($valid->id));
    }

    public function test_deletes_a_token_unused_for_90_plus_days_even_if_not_yet_expired(): void
    {
        $user = $this->makeUser();
        $stale = $this->makeToken($user, [
            'expires_at' => now()->addMonths(3),
            'last_used_at' => now()->subDays(CleanExpiredTokens::DIAS_INACTIVIDAD + 1),
        ]);
        $recent = $this->makeToken($user, [
            'expires_at' => now()->addMonths(3),
            'last_used_at' => now()->subDays(5),
        ]);

        $this->artisan('tokens:clean-expired')->assertExitCode(0);

        $this->assertNull(MobileApiToken::find($stale->id));
        $this->assertNotNull(MobileApiToken::find($recent->id));
    }

    public function test_deletes_a_token_never_used_since_issued_past_the_inactivity_window(): void
    {
        $user = $this->makeUser();
        $neverUsedOld = $this->makeToken($user, [
            'expires_at' => now()->addMonths(3),
            'created_at' => now()->subDays(CleanExpiredTokens::DIAS_INACTIVIDAD + 1),
        ]);
        $neverUsedRecent = $this->makeToken($user, [
            'expires_at' => now()->addMonths(3),
            'created_at' => now()->subDays(2),
        ]);

        $this->artisan('tokens:clean-expired')->assertExitCode(0);

        $this->assertNull(MobileApiToken::find($neverUsedOld->id));
        $this->assertNotNull(MobileApiToken::find($neverUsedRecent->id));
    }

    public function test_leaves_a_recently_used_token_within_expiry_untouched(): void
    {
        $user = $this->makeUser();
        $token = $this->makeToken($user, ['expires_at' => now()->addMonths(3), 'last_used_at' => now()->subDays(10)]);

        $this->artisan('tokens:clean-expired')->assertExitCode(0);

        $this->assertNotNull(MobileApiToken::find($token->id));
    }
}
