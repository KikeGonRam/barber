<?php

namespace Tests\UAT;

use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\CatalogCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * HT-20: el catálogo público va a la caché (60 s) y se borra al editar. En la prueba de carga de staging cada
 * petición del catálogo consultaba Atlas M0 y la API se saturó hacia las 7 req/s.
 */
class CatalogCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Service::query()->delete();
        Product::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();

        parent::tearDown();
    }

    private function servicio(string $nombre): Service
    {
        return Service::create(['nombre' => $nombre, 'categoria' => 'corte', 'precio' => 180, 'duracion_min' => 30, 'activo' => true]);
    }

    private function nombres(string $url): array
    {
        return collect($this->getJson($url)->assertOk()->json('data'))->pluck('nombre')->all();
    }

    public function test_la_segunda_lectura_de_servicios_sale_de_la_cache(): void
    {
        $this->servicio('Corte clásico');
        $this->assertSame(['Corte clásico'], $this->nombres('/api/v1/services'));

        // Un cambio sin eventos de modelo no invalida: la respuesta sigue siendo la guardada.
        Service::query()->update(['nombre' => 'Cambiado por fuera']);

        $this->assertSame(['Corte clásico'], $this->nombres('/api/v1/services'));
        $this->assertTrue(Cache::has(CatalogCache::SERVICES));
    }

    public function test_editar_un_servicio_borra_la_cache(): void
    {
        $servicio = $this->servicio('Corte clásico');
        $this->nombres('/api/v1/services');

        $servicio->update(['nombre' => 'Corte moderno']);

        $this->assertSame(['Corte moderno'], $this->nombres('/api/v1/services'));
    }

    public function test_los_productos_sin_filtro_van_a_la_cache_y_las_busquedas_no(): void
    {
        $pomada = Product::create([
            'nombre' => 'Pomada mate', 'categoria' => 'cuidado', 'descripcion' => 'Prueba', 'precio_compra' => 80,
            'precio_venta' => 220, 'stock_actual' => 5, 'stock_minimo' => 2, 'tipo' => Product::TYPE_SALE, 'activo' => true,
        ]);
        $this->assertSame(['Pomada mate'], $this->nombres('/api/v1/products'));
        $this->assertTrue(Cache::has(CatalogCache::PRODUCTS));

        Product::query()->update(['nombre' => 'Pomada brillante']);

        // La búsqueda consulta directo y ve el cambio; el listado general sigue guardado hasta que algo lo invalide.
        $this->assertSame(['Pomada brillante'], $this->nombres('/api/v1/products?q=Pomada'));
        $this->assertSame(['Pomada mate'], $this->nombres('/api/v1/products'));

        $pomada->refresh()->update(['stock_actual' => 4]);
        $this->assertSame(['Pomada brillante'], $this->nombres('/api/v1/products'));
    }

    public function test_los_datos_de_un_cliente_no_se_guardan_en_la_cache_de_barberos(): void
    {
        $barberoUser = User::create(['name' => 'Nava Panther', 'email' => 'nava-cache@test.local', 'password' => 'password']);
        $barbero = Barber::create(['user_id' => (string) $barberoUser->id, 'nombre' => 'Nava', 'activo' => true]);

        $clienteUser = User::create(['name' => 'Cliente Favorito', 'email' => 'favorito-cache@test.local', 'password' => 'password']);
        Client::create(['user_id' => (string) $clienteUser->id, 'barbero_favorito_id' => (string) $barbero->id]);
        $issued = $clienteUser->issueMobileApiToken('prueba', null, null, MobileApiToken::PLATAFORMA_WEB);

        // El cliente lo ve como favorito...
        $this->withToken($issued['token'])->getJson('/api/v1/barbers')
            ->assertOk()
            ->assertJsonPath('data.0.es_favorito', true)
            ->assertJsonPath('data.0.citas_conmigo', 0);
        $this->assertTrue(Cache::has(CatalogCache::BARBERS));

        // ...pero un visitante sin sesión, servido desde la misma caché, no hereda esos datos.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/barbers')
            ->assertOk()
            ->assertJsonPath('data.0.user.name', 'Nava Panther')
            ->assertJsonPath('data.0.es_favorito', false)
            ->assertJsonPath('data.0.citas_conmigo', null);
    }

    public function test_cambiar_el_nombre_del_barbero_borra_la_cache_de_barberos(): void
    {
        $user = User::create(['name' => 'Nava Panther', 'email' => 'nava-renombre@test.local', 'password' => 'password']);
        Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Nava', 'activo' => true]);
        $this->getJson('/api/v1/barbers')->assertJsonPath('data.0.user.name', 'Nava Panther');

        $user->update(['name' => 'Nava P.']);

        $this->getJson('/api/v1/barbers')->assertJsonPath('data.0.user.name', 'Nava P.');
    }
}
