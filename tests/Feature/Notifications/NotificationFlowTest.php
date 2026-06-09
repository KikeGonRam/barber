<?php

namespace Tests\Feature\Notifications;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use Carbon\Carbon;
use Tests\Support\RefreshMongoDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_appointment_creation_sends_confirmation_notification(): void
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

    public function test_reminder_command_sends_24h_notification(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-03-02 10:15:00'));

        try {
            [$actor, $client, $barber, $service] = $this->bootstrapScenario();

            $appointment = Appointment::query()->create([
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => now()->addDay()->startOfHour()->format('H:i'),
                'hora_fin' => now()->addDay()->startOfHour()->addMinutes(30)->format('H:i'),
                'estado' => 'confirmada',
            ]);

            Artisan::call('appointments:send-reminders');

            Notification::assertSentTo($client->user, AppointmentNotification::class);

            $this->assertNotNull($appointment->fresh()->reminder_24h_sent_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function bootstrapScenario(): array
    {
        $actor = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'recepcionista',
            'guard_name' => 'web',
        ]);
        $actor->assignRole($role);

        $barberUser = User::factory()->create();
        $clientUser = User::factory()->create();

        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
        ]);

        $client = Client::query()->create([
            'user_id' => $clientUser->id,
            'preferencias_notificacion' => [
                'in_app' => true,
                'email' => true,
                'sms' => false,
                'whatsapp' => false,
            ],
        ]);

        $service = Service::query()->create([
            'nombre' => 'Corte clásico',
            'categoria' => 'corte',
            'precio' => 200,
            'duracion_min' => 30,
            'activo' => true,
        ]);

        return [$actor, $client, $barber, $service];
    }
}
