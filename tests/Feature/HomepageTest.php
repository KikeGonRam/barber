<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Regresión: la landing
 * pública (welcome.blade.php) tronaba con un 500 (htmlspecialchars():
 * Argument #1 ($string) must be of type string, array given) cuando
 * Barber::especialidades estaba guardado como array en vez de string —
 * ningún formulario de la app escribe un array ahí (todos validan
 * 'string'), pero un registro cargado fuera de esa validación (como los
 * creados a mano tras el wipe de BD del 2026-09-04) sí puede tenerlo, y
 * nada probaba esta página para detectarlo.
 */
class HomepageTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::flush();
        Barber::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    public function test_homepage_renders_when_a_barber_has_especialidades_as_an_array(): void
    {
        $user = User::create(['name' => 'Barbero Test', 'email' => 'barbero-home@test.local', 'password' => 'password']);
        Barber::create([
            'user_id' => (string) $user->id,
            'nombre' => 'Barbero Test',
            'activo' => true,
            'especialidades' => ['Cortes clásicos', 'Fades'],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Cortes clásicos, Fades', false);
    }

    public function test_homepage_renders_when_a_barber_has_no_especialidades(): void
    {
        $user = User::create(['name' => 'Barbero Sin Especialidad', 'email' => 'barbero-home-2@test.local', 'password' => 'password']);
        Barber::create([
            'user_id' => (string) $user->id,
            'nombre' => 'Barbero Sin Especialidad',
            'activo' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Master Groomer');
    }
}
