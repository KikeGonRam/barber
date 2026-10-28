<?php

namespace Tests\OAT;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Bloqueo automático de IPs que mandan demasiadas peticiones en pocos segundos.
 */
class BlockAbusiveIpsTest extends TestCase
{
    private const URL = '/api/v1/services';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'abuse.enabled' => true,
            'abuse.skip_private' => false, // en las pruebas la IP es 127.0.0.1
            'abuse.max_requests' => 5,
            'abuse.window_seconds' => 60,
            'abuse.ban_seconds' => 600,
            'abuse.max_ban_seconds' => 86400,
            'abuse.allowlist' => [],
        ]);
        Cache::store(config('cache.limiter'))->flush();
    }

    private function hit(string $ip = '203.0.113.10')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson(self::URL);
    }

    public function test_an_ip_that_floods_gets_blocked_with_429_and_retry_after(): void
    {
        foreach (range(1, 5) as $_) {
            $this->hit()->assertOk();
        }

        $blocked = $this->hit();
        $blocked->assertStatus(429);
        $this->assertStringContainsString('no-store', (string) $blocked->headers->get('Cache-Control'));
        $this->assertGreaterThan(0, (int) $blocked->headers->get('Retry-After'));

        // Sigue bloqueada aunque la ventana se reinicie.
        $this->hit()->assertStatus(429);
    }

    public function test_the_block_is_logged_with_the_ip(): void
    {
        $log = Log::spy();

        foreach (range(1, 6) as $_) {
            $this->hit('203.0.113.20');
        }

        $log->shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => $message === 'IP_BLOQUEADA' && $context['ip'] === '203.0.113.20')
            ->once();
    }

    public function test_other_ips_are_not_affected_by_the_block(): void
    {
        foreach (range(1, 6) as $_) {
            $this->hit('203.0.113.30');
        }

        $this->hit('203.0.113.30')->assertStatus(429);
        $this->hit('203.0.113.31')->assertOk();
    }

    public function test_allowlisted_ips_are_never_blocked(): void
    {
        config(['abuse.allowlist' => ['203.0.113.40']]);

        foreach (range(1, 20) as $_) {
            $this->hit('203.0.113.40')->assertOk();
        }
    }

    public function test_repeat_offenders_get_longer_blocks(): void
    {
        $cache = Cache::store(config('cache.limiter'));

        foreach (range(1, 6) as $_) {
            $this->hit('203.0.113.50');
        }
        $first = (int) $cache->get('abuse:ban:203.0.113.50') - time();

        $cache->forget('abuse:ban:203.0.113.50');
        $cache->forget('abuse:hits:203.0.113.50:'.intdiv(time(), 60));
        foreach (range(1, 6) as $_) {
            $this->hit('203.0.113.50');
        }
        $second = (int) $cache->get('abuse:ban:203.0.113.50') - time();

        $this->assertGreaterThan($first + 300, $second);
    }

    public function test_private_addresses_are_skipped_by_default(): void
    {
        config(['abuse.skip_private' => true]);

        foreach (range(1, 20) as $_) {
            $this->hit('10.0.0.5')->assertOk();
        }
    }

    public function test_the_health_check_is_never_counted(): void
    {
        foreach (range(1, 20) as $_) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.60'])->get('/up')->assertOk();
        }
    }
}
