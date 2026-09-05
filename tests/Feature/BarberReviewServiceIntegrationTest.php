<?php

namespace Tests\Feature;

use App\Exceptions\Domain\DuplicateReviewException;
use App\Exceptions\Domain\ReviewNotEligibleException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\Client;
use App\Models\LoyaltyTransaction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Barber\BarberReviewFlaggedNotification;
use App\Services\Barber\BarberReviewService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use MongoDB\Driver\Exception\BulkWriteException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre el punto único de
 * creación de reseñas (antes duplicado e inconsistente entre
 * ClientBarberController y CatalogController): elegibilidad, anti-duplicado
 * (chequeo de aplicación + índice único de respaldo), sincronización de
 * calificacion_promedio/total_resenas del barbero, puntos de lealtad, y el
 * aviso a administración en calificaciones de 1-2 estrellas.
 */
class BarberReviewServiceIntegrationTest extends TestCase
{
    private BarberReviewService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BarberReviewService::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin Reseñas', 'email' => 'admin-resenas@test.local', 'password' => 'password']);
        $this->admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
    }

    protected function tearDown(): void
    {
        BarberReview::query()->delete();
        LoyaltyTransaction::query()->delete();
        Appointment::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function makeBarber(): Barber
    {
        $user = User::create(['name' => 'Barbero Reseñado', 'email' => Str::uuid().'@test.local', 'password' => 'password']);

        return Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Barbero Reseñado', 'activo' => true]);
    }

    private function makeClient(): Client
    {
        $user = User::create(['name' => 'Cliente Reseñador', 'email' => Str::uuid().'@test.local', 'password' => 'password']);

        return Client::create(['user_id' => (string) $user->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
    }

    private function makeCompletedAppointment(Client $client, Barber $barber): void
    {
        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->subDay()->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
        ]);
    }

    public function test_submit_creates_the_review_syncs_barber_stats_and_awards_points(): void
    {
        Notification::fake();
        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $this->makeCompletedAppointment($client, $barber);

        $review = $this->service->submit($client, $barber, 5, 'Excelente corte');

        $this->assertSame(5, $review->rating);
        $this->assertSame('Excelente corte', $review->comment);

        $freshBarber = Barber::find($barber->id);
        $this->assertEquals(5.0, (float) $freshBarber->calificacion_promedio);
        $this->assertSame(1, $freshBarber->total_resenas);

        $this->assertSame(5, Client::find($client->id)->puntos);
        Notification::assertNothingSent();
    }

    public function test_submit_notifies_admins_when_rating_is_two_stars_or_below(): void
    {
        Notification::fake();
        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $this->makeCompletedAppointment($client, $barber);

        $this->service->submit($client, $barber, 1, 'Mal servicio');

        Notification::assertSentTo($this->admin, BarberReviewFlaggedNotification::class, function ($notification) {
            return $notification->rating === 1 && $notification->comment === 'Mal servicio';
        });
    }

    public function test_submit_does_not_notify_admins_when_rating_is_three_or_above(): void
    {
        Notification::fake();
        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $this->makeCompletedAppointment($client, $barber);

        $this->service->submit($client, $barber, 3, 'Normal');

        Notification::assertNothingSent();
    }

    public function test_submit_throws_when_client_never_had_a_completed_appointment(): void
    {
        $client = $this->makeClient();
        $barber = $this->makeBarber();

        $this->expectException(ReviewNotEligibleException::class);

        $this->service->submit($client, $barber, 5, null);
    }

    public function test_submit_throws_when_client_already_reviewed_this_barber(): void
    {
        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $this->makeCompletedAppointment($client, $barber);

        $this->service->submit($client, $barber, 4, null);

        $this->expectException(DuplicateReviewException::class);

        $this->service->submit($client, $barber, 5, null);
    }

    public function test_barber_reviews_collection_rejects_a_duplicate_at_the_database_level(): void
    {
        // Respaldo real del índice único (barber_id, client_id): incluso si
        // el chequeo de aplicación se saltara por una condición de carrera,
        // la base de datos debe rechazar el duplicado. El job de PHPUnit en
        // CI corre los tests sin migrar primero (solo el job de "Smoke test"
        // lo hace), así que el test aplica su propia migración para no
        // depender de que el entorno ya la haya corrido.
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_05_000000_add_barber_reviews_unique_index.php',
            '--force' => true,
        ]);

        $barber = $this->makeBarber();
        $client = $this->makeClient();

        BarberReview::create(['barber_id' => (string) $barber->id, 'client_id' => (string) $client->id, 'rating' => 5]);

        $this->expectException(BulkWriteException::class);

        BarberReview::create(['barber_id' => (string) $barber->id, 'client_id' => (string) $client->id, 'rating' => 3]);
    }
}
