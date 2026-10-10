<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regresión (2026-10-10): en staging las paginas Blade de error pedian la mascota y el icono a
 * https://api.urbanblade.com.mx:8080/..., un puerto interno inalcanzable desde fuera, porque el
 * puerto llegaba en la cabecera Host. Con APP_URL https la raiz de las URLs se fuerza a APP_URL.
 */
class AssetUrlBehindProxyTest extends TestCase
{
    private const PUBLIC_URL = 'https://api.assets-test.example';

    /** @var array<string, string|false> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        // APP_URL se lee al arrancar la app: hay que fijarlo antes de crearla.
        foreach (['APP_URL'] as $key) {
            $this->previousEnv[$key] = getenv($key);
        }
        putenv('APP_URL='.self::PUBLIC_URL);
        $_ENV['APP_URL'] = $_SERVER['APP_URL'] = self::PUBLIC_URL;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->previousEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
    }

    public function test_asset_urls_do_not_leak_the_internal_port(): void
    {
        $this->assertSame(self::PUBLIC_URL, config('app.url'), 'APP_URL no se aplicó antes de arrancar la app.');

        $response = $this->get('https://api.assets-test.example:8080/ruta-que-no-existe', ['Accept' => 'text/html']);

        $response->assertNotFound();
        $html = $response->getContent();

        $this->assertStringContainsString(self::PUBLIC_URL.'/images/urbanblade-mark.svg', $html);
        $this->assertStringContainsString(self::PUBLIC_URL.'/images/mascots/', $html);
        $this->assertStringNotContainsString(':8080', $html, 'Las URLs de la página filtran el puerto interno.');
    }
}
