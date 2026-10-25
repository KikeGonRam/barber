<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * El catálogo público no tiene sesión que limitar, así que se limita por IP (limitador «catalog»).
 */
class CatalogThrottleTest extends TestCase
{
    public function test_public_catalog_routes_use_the_catalog_limiter(): void
    {
        foreach (['api/v1/services', 'api/v1/barbers', 'api/v1/products', 'api/v1/barbershop', 'api/v1/social/feed'] as $uri) {
            $route = collect(RouteFacade::getRoutes()->getRoutes())
                ->first(fn (Route $r) => $r->uri() === $uri && in_array('GET', $r->methods(), true));

            $this->assertNotNull($route, "No existe la ruta GET {$uri}");
            $this->assertContains('throttle:catalog', $route->gatherMiddleware(), "{$uri} no usa throttle:catalog");
        }
    }

    public function test_the_catalog_limiter_allows_many_requests_but_caps_the_flood(): void
    {
        $limiter = RateLimiter::limiter('catalog');
        $this->assertNotNull($limiter);

        $limit = $limiter(request());
        $this->assertSame(1200, $limit->maxAttempts);
        $this->assertSame(60, $limit->decaySeconds);
    }

    public function test_a_normal_visit_to_the_catalog_is_not_throttled(): void
    {
        foreach (range(1, 5) as $_) {
            $this->getJson('/api/v1/services')->assertOk();
        }
    }
}
