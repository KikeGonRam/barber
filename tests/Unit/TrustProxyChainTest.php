<?php

namespace Tests\Unit;

use App\Http\Middleware\TrustProxyChain;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Detrás de CloudFront + ALB, REMOTE_ADDR es siempre el ALB. Estas pruebas fijan que
 * con TRUSTED_PROXY_HOPS=2 la IP resultante sea la del visitante, que una
 * X-Forwarded-For falsificada no la cambie, y que local (0) siga sin confiar en nada.
 *
 * IPs de ejemplo: 10.0.1.5 = ALB (privada de la VPC), 130.176.1.1 = nodo de
 * CloudFront, 203.0.113.7 = visitante real, 6.6.6.6 = valor inventado por el cliente.
 */
class TrustProxyChainTest extends TestCase
{
    private const ALB = '10.0.1.5';

    private const CLOUDFRONT = '130.176.1.1';

    private const VISITANTE = '203.0.113.7';

    protected function tearDown(): void
    {
        // setTrustedProxies es estado estático de Symfony: no debe filtrarse a otras pruebas.
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);

        parent::tearDown();
    }

    /** @param array<string, string> $server */
    private function clientIp(int $hops, array $server): ?string
    {
        config()->set('app.trusted_proxy_hops', $hops);

        $request = Request::create('/api/v1/services', 'GET', [], [], [], $server + ['REMOTE_ADDR' => self::ALB]);

        $ip = null;
        (new TrustProxyChain)->handle($request, function (Request $r) use (&$ip) {
            $ip = $r->ip();

            return new Response('ok');
        });

        return $ip;
    }

    public function test_esta_registrado_en_lugar_del_trust_proxies_de_laravel(): void
    {
        $this->assertTrue($this->app->make(Kernel::class)->hasMiddleware(TrustProxyChain::class));
    }

    public function test_sin_saltos_configurados_no_confia_en_x_forwarded_for(): void
    {
        $ip = $this->clientIp(0, ['HTTP_X_FORWARDED_FOR' => self::VISITANTE.', '.self::CLOUDFRONT]);

        $this->assertSame(self::ALB, $ip);
    }

    public function test_con_cloudfront_y_alb_devuelve_la_ip_del_visitante(): void
    {
        $ip = $this->clientIp(2, ['HTTP_X_FORWARDED_FOR' => self::VISITANTE.', '.self::CLOUDFRONT]);

        $this->assertSame(self::VISITANTE, $ip);
    }

    public function test_una_x_forwarded_for_falsificada_no_cambia_la_ip(): void
    {
        $ip = $this->clientIp(2, ['HTTP_X_FORWARDED_FOR' => '6.6.6.6, '.self::VISITANTE.', '.self::CLOUDFRONT]);

        $this->assertSame(self::VISITANTE, $ip);
    }

    public function test_sin_x_forwarded_for_usa_la_ip_de_la_conexion(): void
    {
        // Así llegan los health checks del ALB.
        $this->assertSame(self::ALB, $this->clientIp(2, []));
    }

    public function test_no_confia_en_el_puerto_ni_el_protocolo_que_manda_el_alb(): void
    {
        config()->set('app.trusted_proxy_hops', 2);

        $request = Request::create('/api/v1/services', 'GET', [], [], [], [
            'REMOTE_ADDR' => self::ALB,
            'SERVER_PORT' => 80,
            'HTTP_X_FORWARDED_FOR' => self::VISITANTE.', '.self::CLOUDFRONT,
            'HTTP_X_FORWARDED_PORT' => '8080',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        (new TrustProxyChain)->handle($request, function (Request $r) {
            // Si se confiara en X-Forwarded-Port, Laravel generaría URLs con ":8080".
            $this->assertEquals(80, $r->getPort());
            $this->assertSame('http', $r->getScheme());

            return new Response('ok');
        });
    }
}
