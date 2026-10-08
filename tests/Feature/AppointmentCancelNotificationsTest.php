<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Appointment\AppointmentNotification;
use App\Notifications\Channels\FcmPushChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Appointment\AppointmentManageLinkService;
use App\Services\Payment\StripePaymentService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cuando un cliente cancela su cita, él, el barbero y el personal reciben el aviso, incluso si el
 * reembolso del depósito por Stripe falla (el aviso de cancelación no puede depender del reembolso).
 */
class AppointmentCancelNotificationsTest extends TestCase
{
    private User $clientUser;

    private User $barberUser;

    private User $adminUser;

    private string $token;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();

        $this->clientUser = $this->userWithRole('cliente', 'cliente-cancela@test.local');
        $client = Client::create(['user_id' => (string) $this->clientUser->id, 'telefono' => '5551112222', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $this->barberUser = $this->userWithRole('barbero', 'barbero-cancela@test.local');
        $barber = Barber::create(['user_id' => (string) $this->barberUser->id, 'nombre' => 'Barbero', 'activo' => true]);
        $this->adminUser = $this->userWithRole('administrador', 'admin-cancela@test.local');
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        $this->appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDays(6)->format('Y-m-d'), 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'estado' => 'confirmada',
        ]);

        $this->token = 'token-cliente-'.uniqid();
        MobileApiToken::create(['user_id' => (string) $this->clientUser->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->token), 'expires_at' => now()->addDay()]);
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        Payment::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function userWithRole(string $roleName, string $email): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName), 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id], 'notification_preferences' => ['push' => true]])->save();

        return $user;
    }

    private function cancel(): TestResponse
    {
        return $this->withToken($this->token)->deleteJson('/api/v1/appointments/'.$this->appointment->getAttribute('code'));
    }

    private function assertSentCancelled(User $user, string $title): void
    {
        Notification::assertSentTo($user, AppointmentNotification::class, fn (AppointmentNotification $n): bool => $n->title === $title);
    }

    public function test_cancelling_notifies_client_barber_and_staff(): void
    {
        $this->cancel()->assertOk();

        $this->assertSame('cancelada', $this->appointment->fresh()->estado);
        $this->assertSentCancelled($this->clientUser, 'Tu cita fue cancelada');
        $this->assertSentCancelled($this->barberUser, 'Se canceló una cita');
        $this->assertSentCancelled($this->adminUser, 'Se canceló una cita');
        $this->assertNotNull($this->appointment->fresh()->cancellation_notified_at);
    }

    public function test_cancelling_still_notifies_when_the_stripe_refund_fails(): void
    {
        Payment::create([
            'appointment_id' => (string) $this->appointment->id, 'monto' => 100, 'propina' => 0, 'metodo_pago' => 'tarjeta',
            'es_deposito' => true, 'estado' => Payment::ESTADO_VERIFICADO, 'stripe_payment_id' => 'pi_falla',
        ]);
        $this->mock(StripePaymentService::class, fn ($mock) => $mock->shouldReceive('refund')->once()->andThrow(new \RuntimeException('No such payment_intent')));

        $this->cancel()->assertOk();

        $this->assertSentCancelled($this->adminUser, 'No se pudo reembolsar un depósito automáticamente');
        $this->assertSentCancelled($this->clientUser, 'Tu cita fue cancelada');
        $this->assertSentCancelled($this->barberUser, 'Se canceló una cita');
    }

    public function test_the_cancellation_push_reaches_the_client_when_push_is_on(): void
    {
        $this->cancel()->assertOk();

        Notification::assertSentTo($this->clientUser, AppointmentNotification::class, function (AppointmentNotification $n, array $channels): bool {
            return in_array(FcmPushChannel::class, $channels, true)
                && in_array(WebPushChannel::class, $channels, true);
        });
    }

    private function adminToken(): string
    {
        $plain = 'token-admin-'.uniqid();
        MobileApiToken::create(['user_id' => (string) $this->adminUser->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDay()]);

        return $plain;
    }

    public function test_staff_cancelling_from_the_agenda_notifies_client_barber_and_staff(): void
    {
        // Regresión 7-oct-2026: PATCH /status con «cancelada» cambiaba la cita pero no mandaba ningún
        // aviso (ni push), porque statusChanged() ignora las cancelaciones.
        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/appointments/'.$this->appointment->getAttribute('code').'/status', ['estado' => 'cancelada'])
            ->assertOk();

        $this->assertSame('cancelada', $this->appointment->fresh()->estado);
        $this->assertSentCancelled($this->clientUser, 'Tu cita fue cancelada');
        $this->assertSentCancelled($this->barberUser, 'Se canceló una cita');
        $this->assertSentCancelled($this->adminUser, 'Se canceló una cita');
        Notification::assertSentTo($this->barberUser, AppointmentNotification::class, fn (AppointmentNotification $n): bool => str_contains($n->message, 'Se canceló tu cita con'));
        $this->assertNotNull($this->appointment->fresh()->cancellation_notified_at);
    }

    public function test_staff_cancelling_with_a_failing_refund_still_notifies_the_client(): void
    {
        Payment::create([
            'appointment_id' => (string) $this->appointment->id, 'monto' => 100, 'propina' => 0, 'metodo_pago' => 'tarjeta',
            'es_deposito' => true, 'estado' => Payment::ESTADO_VERIFICADO, 'stripe_payment_id' => 'pi_falla',
        ]);
        $this->mock(StripePaymentService::class, fn ($mock) => $mock->shouldReceive('refund')->once()->andThrow(new \RuntimeException('No such payment_intent')));

        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/appointments/'.$this->appointment->getAttribute('code').'/status', ['estado' => 'cancelada'])
            ->assertOk();

        $this->assertSentCancelled($this->clientUser, 'Tu cita fue cancelada');
        $this->assertSentCancelled($this->adminUser, 'No se pudo reembolsar un depósito automáticamente');
    }

    public function test_editing_the_appointment_to_cancelled_notifies_the_client(): void
    {
        $this->withToken($this->adminToken())
            ->putJson('/api/v1/appointments/'.$this->appointment->getAttribute('code'), [
                'client_id' => (string) $this->appointment->client_id,
                'barber_id' => (string) $this->appointment->barber_id,
                'service_id' => (string) $this->appointment->service_id,
                'fecha' => Carbon::parse($this->appointment->getAttribute('fecha'))->format('Y-m-d'),
                'hora_inicio' => '11:00',
                'estado' => 'cancelada',
            ])
            ->assertOk();

        $this->assertSame('cancelada', $this->appointment->fresh()->estado);
        $this->assertSentCancelled($this->clientUser, 'Tu cita fue cancelada');
        $this->assertSentCancelled($this->barberUser, 'Se canceló una cita');
    }

    public function test_cancelling_from_the_reminder_link_notifies_everyone(): void
    {
        $token = app(AppointmentManageLinkService::class)->tokenFor($this->appointment);

        $this->postJson('/api/v1/appointments/'.$this->appointment->getAttribute('code').'/manage/cancel?t='.$token)->assertOk();

        $this->assertSame('cancelada', $this->appointment->fresh()->estado);
        $this->assertSentCancelled($this->clientUser, 'Tu cita fue cancelada');
        $this->assertSentCancelled($this->barberUser, 'Se canceló una cita');
        $this->assertSentCancelled($this->adminUser, 'Se canceló una cita');
    }

    public function test_the_barber_message_says_the_client_cancelled_only_when_the_client_did(): void
    {
        $this->cancel()->assertOk();

        Notification::assertSentTo($this->barberUser, AppointmentNotification::class, fn (AppointmentNotification $n): bool => str_contains($n->message, 'canceló su cita contigo'));
    }
}
