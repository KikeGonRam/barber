<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Barber\BarberPerformanceReportNotification;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre que el comando
 * mensual realmente notifica a los administradores (no solo calcula el
 * reporte en memoria) y que se queda callado cuando no hay nada que reportar.
 */
class BarberMonthlyPerformanceCommandTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin de prueba', 'email' => 'admin-barberperf@test.local', 'password' => 'password']);
        $this->admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
    }

    protected function tearDown(): void
    {
        Appointment::query()->delete();
        Barber::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function makeBarber(string $name): Barber
    {
        $user = User::create(['name' => $name, 'email' => Str::uuid().'@test.local', 'password' => 'password']);

        return Barber::create(['user_id' => (string) $user->id, 'nombre' => $name, 'activo' => true]);
    }

    private function makeCompletedAppointments(Barber $barber, Carbon $inMonth, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Appointment::create([
                'client_id' => (string) Str::uuid(),
                'barber_id' => (string) $barber->id,
                'service_id' => (string) Str::uuid(),
                'fecha' => $inMonth->copy()->startOfMonth()->addDays($i % 25)->format('Y-m-d'),
                'hora_inicio' => '09:00:00',
                'hora_fin' => '09:30:00',
                'estado' => 'completada',
            ]);
        }
    }

    public function test_notifies_admins_with_the_report_when_there_is_a_drop(): void
    {
        Notification::fake();

        $barber = $this->makeBarber('Barbero En Caida');
        $this->makeCompletedAppointments($barber, now()->subMonthsNoOverflow(2), 10);
        $this->makeCompletedAppointments($barber, now()->subMonthNoOverflow(), 2);

        $this->artisan('barbers:monthly-performance')->assertExitCode(0);

        Notification::assertSentTo($this->admin, BarberPerformanceReportNotification::class);
    }

    public function test_dry_run_does_not_send_notifications(): void
    {
        Notification::fake();

        $barber = $this->makeBarber('Barbero En Caida');
        $this->makeCompletedAppointments($barber, now()->subMonthsNoOverflow(2), 10);
        $this->makeCompletedAppointments($barber, now()->subMonthNoOverflow(), 2);

        $this->artisan('barbers:monthly-performance', ['--dry-run' => true])->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_sends_nothing_when_there_is_no_top_performer_or_drop(): void
    {
        Notification::fake();

        $this->artisan('barbers:monthly-performance')->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
