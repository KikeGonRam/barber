<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * data:prepare-production: el simulacro no toca nada, --apply exige confirmar el respaldo y, al aplicar, borra el
 * movimiento y SOLO la cuenta de prueba indicada (las demás cuentas, con sus perfiles, se conservan).
 */
class PrepareProductionDataCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();

        parent::tearDown();
    }

    private function cleanup(): void
    {
        Client::query()->delete();
        User::withTrashed()->forceDelete();
        DB::connection('mongodb')->table('payments')->delete();
        DB::connection('mongodb')->table('push_subscriptions')->delete();
        foreach (['works', 'work_images', 'comments', 'reactions', 'barbers'] as $collection) {
            DB::connection('mongodb')->table($collection)->delete();
        }
    }

    public function test_apply_removes_portfolio_of_missing_barbers_and_keeps_real_ones(): void
    {
        $db = DB::connection('mongodb');
        $realBarberId = (string) $db->table('barbers')->insertGetId(['nombre' => 'Barbero Real']);
        $keptWork = (string) $db->table('works')->insertGetId(['barbero_id' => $realBarberId, 'title' => 'Real']);
        $orphanWork = (string) $db->table('works')->insertGetId(['barbero_id' => '000000000000000000000000', 'title' => 'Seed']);
        $db->table('work_images')->insert([['work_id' => $keptWork, 'image' => 'a'], ['work_id' => $orphanWork, 'image' => 'b']]);
        $db->table('comments')->insert(['work_id' => $orphanWork, 'body' => 'x']);

        $this->artisan('data:prepare-production')->assertExitCode(0);
        $this->assertSame(2, $db->table('works')->count(), 'El simulacro no borra nada.');

        $this->artisan('data:prepare-production', ['--apply' => true, '--confirm-backup' => true])->assertExitCode(0);

        $this->assertSame(1, $db->table('works')->count());
        $this->assertSame(1, $db->table('works')->where('title', 'Real')->count());
        $this->assertSame(1, $db->table('work_images')->count());
        $this->assertSame(0, $db->table('comments')->count());
    }

    /** @return array{0: User, 1: User} [real, prueba] */
    private function seedAccounts(): array
    {
        $real = User::create(['name' => 'Cuenta Real', 'email' => 'real-'.uniqid().'@test.local', 'password' => 'password']);
        Client::create(['user_id' => (string) $real->id, 'telefono' => '5550001111', 'nivel' => 'oro', 'puntos' => 90, 'total_citas' => 9]);
        $test = User::create(['name' => 'Test de UrbanBlade', 'email' => 'prueba-'.uniqid().'@test.local', 'password' => 'password']);
        Client::create(['user_id' => (string) $test->id, 'telefono' => '5550002222', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        DB::connection('mongodb')->table('push_subscriptions')->insert([
            ['user_id' => (string) $test->id, 'endpoint' => 'https://push.test/prueba'],
            ['user_id' => (string) $real->id, 'endpoint' => 'https://push.test/real'],
        ]);
        DB::connection('mongodb')->table('payments')->insert(['monto' => 100]);

        return [$real, $test];
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->seedAccounts();

        $this->artisan('data:prepare-production')->expectsOutputToContain('Nada cambió')->assertExitCode(0);

        $this->assertSame(2, User::count());
        $this->assertSame(1, DB::connection('mongodb')->table('payments')->count());
    }

    public function test_apply_requires_backup_confirmation(): void
    {
        $this->seedAccounts();

        $this->artisan('data:prepare-production', ['--apply' => true])->assertExitCode(1);

        $this->assertSame(2, User::count());
    }

    public function test_apply_deletes_movement_and_only_the_test_account(): void
    {
        [$real, $test] = $this->seedAccounts();

        $this->artisan('data:prepare-production', ['--apply' => true, '--confirm-backup' => true])->assertExitCode(0);

        $this->assertNull(User::find($test->id));
        $this->assertNotNull(User::find($real->id));
        $this->assertSame(1, Client::count());
        $this->assertSame(0, DB::connection('mongodb')->table('payments')->count());
        $this->assertSame(1, DB::connection('mongodb')->table('push_subscriptions')->count());

        $kept = Client::where('user_id', (string) $real->id)->first();
        $this->assertSame(0, (int) $kept->puntos, 'Los contadores de lealtad se reinician porque su historial se borró.');
    }
}
