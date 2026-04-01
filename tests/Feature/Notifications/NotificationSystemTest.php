<?php

namespace Tests\Feature\Notifications;

/**
 * Cubre sistema de notificaciones: canales, recordatorios,
 * marcado de lectura y visibilidad de badge.
 */

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
            'verified',
        ]);

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
        ]);
    }

    // --- Contexto: creacion de cita y canales ---

    public function test_creating_appointment_sends_in_app_notification_to_client(): void
    {
        Notification::fake();

        [$actor, $client, $barber, $service] = $this->bootstrapScenario();

        $this->actingAs($actor)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => '10:00',
                'hora_fin' => '10:30',
                'estado' => 'confirmada',
            ])
            ->assertRedirect(route('appointments.index'));

        Notification::assertSentTo($client->user, AppointmentNotification::class);
    }

    public function test_client_with_email_true_receives_mail_channel_on_appointment_creation(): void
    {
        Notification::fake();

        [$actor, $client, $barber, $service] = $this->bootstrapScenario([
            'in_app' => true,
            'email' => true,
        ]);

        $this->actingAs($actor)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => '10:00',
                'hora_fin' => '10:30',
                'estado' => 'confirmada',
            ])
            ->assertRedirect(route('appointments.index'));

        Notification::assertSentTo($client->user, AppointmentNotification::class, function ($notification, $channels): bool {
            return in_array('mail', $channels, true);
        });
    }

    public function test_client_with_email_false_does_not_receive_mail_channel_on_creation(): void
    {
        Notification::fake();

        [$actor, $client, $barber, $service] = $this->bootstrapScenario([
            'in_app' => true,
            'email' => false,
        ]);

        $this->actingAs($actor)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => '10:00',
                'hora_fin' => '10:30',
                'estado' => 'confirmada',
            ])
            ->assertRedirect(route('appointments.index'));

        Notification::assertSentTo($client->user, AppointmentNotification::class, function ($notification, $channels): bool {
            return ! in_array('mail', $channels, true) && in_array('database', $channels, true);
        });
    }

    // --- Contexto: comando recordatorios ---

    public function test_send_reminders_command_sends_only_appointments_for_tomorrow(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-01 10:15:00'));

        try {
            [$actor, $client, $barber, $service] = $this->bootstrapScenario();

            $tomorrowAppointment = Appointment::query()->create([
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => now()->addDay()->startOfHour()->format('H:i'),
                'hora_fin' => now()->addDay()->startOfHour()->addMinutes(30)->format('H:i'),
                'estado' => 'confirmada',
            ]);

            Appointment::query()->create([
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDays(2)->toDateString(),
                'hora_inicio' => now()->addDays(2)->startOfHour()->format('H:i'),
                'hora_fin' => now()->addDays(2)->startOfHour()->addMinutes(30)->format('H:i'),
                'estado' => 'confirmada',
            ]);

            Artisan::call('appointments:send-reminders');

            Notification::assertSentToTimes($client->user, AppointmentNotification::class, 1);
            $this->assertNotNull($tomorrowAppointment->fresh()->reminder_24h_sent_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_send_reminders_does_not_resend_when_reminder_24h_already_set(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-01 10:15:00'));

        try {
            [$actor, $client, $barber, $service] = $this->bootstrapScenario();

            Appointment::query()->create([
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => now()->addDay()->startOfHour()->format('H:i'),
                'hora_fin' => now()->addDay()->startOfHour()->addMinutes(30)->format('H:i'),
                'estado' => 'confirmada',
                'reminder_24h_sent_at' => now()->subHour(),
            ]);

            Artisan::call('appointments:send-reminders');

            Notification::assertNothingSent();
        } finally {
            Carbon::setTestNow();
        }
    }

    // --- Contexto: lectura y badge ---

    public function test_mark_all_read_sets_unread_notifications_count_to_zero(): void
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AppointmentNotification::class,
            'data' => [
                'title' => 'Notif A',
                'message' => 'Mensaje A',
            ],
            'read_at' => null,
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AppointmentNotification::class,
            'data' => [
                'title' => 'Notif B',
                'message' => 'Mensaje B',
            ],
            'read_at' => null,
        ]);

        $this->assertGreaterThan(0, $user->unreadNotifications()->count());

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_notification_badge_is_visible_when_user_has_unread_notifications(): void
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        $user->notify(new AppointmentNotification(
            appointment: Appointment::factory()->create(),
            subject: 'Notif Badge',
            title: 'Notif Badge',
            message: 'Mensaje Badge',
        ));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notificaciones')
            ->assertSee('1');
    }

    public function test_notification_badge_disappears_after_marking_all_as_read(): void
    {
        $user = $this->createVerifiedUserWithRole('cliente');

        $user->notify(new AppointmentNotification(
            appointment: Appointment::factory()->create(),
            subject: 'Notif Read',
            title: 'Notif Read',
            message: 'Mensaje Read',
        ));

        $this->actingAs($user)->post(route('notifications.read-all'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notificaciones');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    private function bootstrapScenario(array $prefs = ['in_app' => true, 'email' => true]): array
    {
        $actor = $this->createVerifiedUserWithRole('recepcionista');
        $barberUser = User::factory()->create();
        $clientUser = User::factory()->create();

        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
        ]);

        $client = Client::query()->create([
            'user_id' => $clientUser->id,
            'preferencias_notificacion' => [
                'in_app' => (bool) ($prefs['in_app'] ?? true),
                'email' => (bool) ($prefs['email'] ?? true),
                'sms' => false,
                'whatsapp' => false,
            ],
        ]);

        $service = Service::query()->create([
            'nombre' => 'Corte Notif',
            'categoria' => 'corte',
            'precio' => 200,
            'duracion_min' => 30,
            'activo' => true,
        ]);

        return [$actor, $client, $barber, $service];
    }

    private function createVerifiedUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
