<?php

namespace Tests\SIT;

use App\Models\MobileApiToken;
use App\Models\User;
use Tests\TestCase;

/**
 * routes/api.php envuelve cada grupo de rutas de los módulos apagables (config/features.php) en
 * un `if (config('features.xxx'))`, evaluado UNA VEZ cuando la aplicación de pruebas arranca
 * (ver bootstrap/app.php y Illuminate\Foundation\Testing\TestCase::createApplication(), llamado
 * desde setUp() en cada método de prueba). Por eso NO basta con `config(['features.x' => false])`
 * a mitad de una prueba -- la ruta ya se registró o no se registró antes de que el cuerpo de la
 * prueba corra. Hay que fijar la variable de entorno con putenv() ANTES de parent::setUp(), que
 * es quien dispara createApplication().
 *
 * phpunit.xml fija FEATURE_PACKAGES=true, FEATURE_GIFT_CARDS=true, FEATURE_REFERRALS=true,
 * FEATURE_RAFFLES=true, FEATURE_SOCIAL=true y FEATURE_CAMPAIGNS=true a propósito (para que la
 * suite existente de esos módulos siga pasando sin tocarla), aunque el valor real por defecto
 * sin esas env es `false` (ver config/features.php). Cada prueba "apagada" de abajo restaura su
 * variable en tearDown() para no afectar los demás tests del mismo proceso PHPUnit.
 */
class FeatureFlagRoutesTest extends TestCase
{
    /** @var array<int, string> nombres de variables de entorno que esta clase puede haber tocado, para restaurarlas siempre */
    private array $touchedEnvVars = [];

    /**
     * Deliberadamente vacío: Tests\TestCase::setUp() (heredado) es quien, a través de
     * Illuminate\Foundation\Testing\TestCase::setUp(), dispara createApplication() -- si lo
     * dejáramos correr aquí automáticamente (como en cualquier otra prueba), la aplicación ya
     * arrancaría con el valor de phpunit.xml antes de que el cuerpo de la prueba pudiera hacer
     * putenv(). Cada prueba de abajo llama a parent::setUp() ella misma, después de fijar sus
     * variables de entorno.
     */
    protected function setUp(): void
    {
        // Intencionalmente no se llama a parent::setUp() aquí.
    }

    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();

        // Se restaura el valor de phpunit.xml en los tres lugares donde Laravel puede leerlo.
        foreach ($this->touchedEnvVars as $name) {
            $this->setEnv($name, 'true');
        }
        $this->touchedEnvVars = [];

        parent::tearDown();
    }

    /**
     * Laravel lee $_ENV y $_SERVER antes que getenv(): con solo putenv() el valor de phpunit.xml
     * (que PHPUnit deja en $_ENV) ganaría y el interruptor no se apagaría. Mismo patrón que
     * tests/OAT/PublicUploadsCacheControlTest.php.
     */
    private function setEnv(string $name, string $value): void
    {
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private function turnOff(string $envVar): void
    {
        $this->setEnv($envVar, 'false');
        $this->touchedEnvVars[] = $envVar;
    }

    private function tokenForAnyAuthenticatedUser(): string
    {
        $user = User::create(['name' => 'Cliente Flags', 'email' => 'cliente-flags-'.uniqid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now()])->save();

        $plaintext = 'test-token-flags-'.uniqid();
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
    }

    public function test_packages_routes_do_not_exist_when_the_switch_is_off(): void
    {
        $this->turnOff('FEATURE_PACKAGES');
        parent::setUp();

        $token = $this->tokenForAnyAuthenticatedUser();

        $this->withToken($token)->getJson('/api/v1/packages/catalog')->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/packages')->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/packages/stripe-intent')->assertNotFound();
    }

    public function test_packages_routes_exist_when_the_switch_is_on(): void
    {
        // Sin putenv: usa el valor normal de phpunit.xml (FEATURE_PACKAGES=true).
        parent::setUp();

        $token = $this->tokenForAnyAuthenticatedUser();

        // 200, no 404: la ruta existe y responde (no se prueba aquí el contenido del catálogo).
        $this->withToken($token)->getJson('/api/v1/packages/catalog')->assertOk();
    }

    public function test_social_feed_route_does_not_exist_when_the_switch_is_off(): void
    {
        $this->turnOff('FEATURE_SOCIAL');
        parent::setUp();

        // social/feed usa mobile.auth.optional: sin token también debe dar 404 (no existe),
        // nunca 401 (que implicaría que la ruta sí está registrada).
        $this->getJson('/api/v1/social/feed')->assertNotFound();
    }

    public function test_social_feed_route_exists_when_the_switch_is_on(): void
    {
        parent::setUp();

        $this->getJson('/api/v1/social/feed')->assertOk();
    }
}
