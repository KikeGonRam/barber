<?php

namespace Tests\UAT;

use Tests\TestCase;

/**
 * El frontend (Nuxt) llama a la API desde otro dominio con Authorization, así que cada llamada va
 * precedida de un preflight OPTIONS. Si la respuesta dice max-age 0 el navegador no lo cachea y
 * duplica los viajes de red de toda la web.
 */
class CorsPreflightTest extends TestCase
{
    public function test_preflight_can_be_cached_by_the_browser(): void
    {
        $origin = (string) (config('cors.allowed_origins')[0] ?? 'http://localhost:3000');

        $response = $this->call('OPTIONS', '/api/v1/auth/me', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ]);

        $response->assertNoContent();
        $this->assertSame('7200', $response->headers->get('Access-Control-Max-Age'));
    }
}
