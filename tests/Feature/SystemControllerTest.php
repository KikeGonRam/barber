<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\System\ScheduledTaskMonitor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cubre el contenido de GET /api/v1/admin/system/status (Fase 3 del plan
 * Stripe+ops-role): forma de la respuesta y que ScheduledTaskMonitor
 * refleje correctamente una tarea exitosa y una fallida.
 */
class SystemControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forget('schedule:last-run:appointments:send-reminders');
        Cache::forget('schedule:last-run:tokens:clean-expired');

        parent::tearDown();
    }

    private function ingenieroToken(): string
    {
        $role = Role::where('name', 'ingeniero')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Ingeniero Status', 'email' => 'ingeniero-status@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $plaintext = 'test-token-ingeniero-status';
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
    }

    public function test_status_endpoint_returns_the_expected_shape(): void
    {
        $token = $this->ingenieroToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/admin/system/status');

        $response->assertOk();
        $response->assertJsonStructure([
            'app' => ['name', 'env', 'laravel_version', 'php_version'],
            'database' => ['status', 'latency_ms'],
            'redis' => ['status', 'latency_ms'],
            'queue' => ['connection', 'pending', 'failed'],
            'scheduled_tasks' => [
                '*' => ['name', 'expression', 'status', 'ran_at', 'runtime_ms', 'error'],
            ],
        ]);

        // Cubre las 12 tareas reales de routes/console.php -- si alguna vez
        // se agrega/quita una tarea ahí sin querer, este número lo delata.
        $this->assertCount(12, $response->json('scheduled_tasks'));
    }

    public function test_scheduled_task_monitor_records_success_and_failure(): void
    {
        $monitor = app(ScheduledTaskMonitor::class);

        $event = new Event(app(CacheEventMutex::class), 'php artisan appointments:send-reminders');
        $event->description = 'appointments:send-reminders';

        $monitor->recordFinished(new ScheduledTaskFinished($event, 1.234));
        $success = $monitor->lastRun('appointments:send-reminders');
        $this->assertSame('success', $success['status']);
        $this->assertSame(1234, $success['runtime_ms']);
        $this->assertNull($success['error']);

        $failedEvent = new Event(app(CacheEventMutex::class), 'php artisan tokens:clean-expired');
        $failedEvent->description = 'tokens:clean-expired';

        $monitor->recordFailed(new ScheduledTaskFailed($failedEvent, new \RuntimeException('boom')));
        $failed = $monitor->lastRun('tokens:clean-expired');
        $this->assertSame('failed', $failed['status']);
        $this->assertSame('boom', $failed['error']);
    }
}
