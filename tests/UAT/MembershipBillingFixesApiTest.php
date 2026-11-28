<?php

namespace Tests\UAT;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\MembershipPlan;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Services\Payment\StripePaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cobros de membresía: borrar una cuenta deja de cobrarla en Stripe, y anular un cobro
 * no deja el precio rebajado como base del siguiente.
 */
class MembershipBillingFixesApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        User::withTrashed()->forceDelete();

        foreach ([ClientMembership::class, MembershipPlan::class, Payment::class, Service::class, Barber::class, Client::class, MobileApiToken::class, Role::class, Permission::class] as $model) {
            $model::query()->delete();
        }

        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName).' Membresia', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        return $user;
    }

    private function tokenFor(User $user): string
    {
        $plaintext = (string) Str::uuid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plaintext)]);

        return $plaintext;
    }

    /** @return array{0: User, 1: Client, 2: ClientMembership} */
    private function member(int $descuentoPct = 10): array
    {
        $user = $this->userWithRole('cliente');
        $client = Client::create(['user_id' => (string) $user->id, 'telefono' => '5551230000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $plan = MembershipPlan::create([
            'nombre' => 'Plan', 'precio_mensual' => 199, 'descuento_pct' => $descuentoPct,
            'stripe_product_id' => 'prod_test', 'stripe_price_id' => 'price_test', 'activo' => true,
        ]);
        $membership = ClientMembership::create([
            'client_id' => (string) $client->id, 'membership_plan_id' => (string) $plan->id,
            'stripe_customer_id' => 'cus_test', 'stripe_subscription_id' => 'sub_member',
            'estado' => ClientMembership::ESTADO_ACTIVA, 'bloquea_membresia' => true,
            'cancelar_al_finalizar' => false, 'periodo_actual_fin' => now()->addWeeks(2),
        ]);

        return [$user, $client, $membership];
    }

    private function appointmentFor(Client $client): Appointment
    {
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte Membresia', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'estado' => 'confirmada',
        ]);
    }

    public function test_deleting_own_account_cancels_the_stripe_subscription(): void
    {
        [$user, , $membership] = $this->member();
        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('cancelSubscriptionNow')->once()->with('sub_member');
        });

        $this->withToken($this->tokenFor($user))
            ->deleteJson('/api/v1/profile', ['password' => 'password'])
            ->assertOk();

        $this->assertNull(User::find($user->id));
        $this->assertSame(ClientMembership::ESTADO_CANCELADA, $membership->fresh()->getAttribute('estado'));
        $this->assertFalse($membership->fresh()->getAttribute('bloquea_membresia'));
    }

    public function test_the_account_is_kept_when_stripe_cannot_cancel(): void
    {
        [$user, , $membership] = $this->member();
        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('cancelSubscriptionNow')->andThrow(new \RuntimeException('Stripe no responde'));
        });

        $this->withToken($this->tokenFor($user))
            ->deleteJson('/api/v1/profile', ['password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('membresia');

        $this->assertNotNull(User::find($user->id));
        $this->assertSame(1, MobileApiToken::where('user_id', (string) $user->id)->count());
        $this->assertSame(ClientMembership::ESTADO_ACTIVA, $membership->fresh()->getAttribute('estado'));
    }

    public function test_an_admin_deleting_a_member_cancels_the_subscription(): void
    {
        [$user, , $membership] = $this->member();
        $admin = $this->userWithRole('administrador');
        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('cancelSubscriptionNow')->once()->with('sub_member');
        });

        $this->withToken($this->tokenFor($admin))->deleteJson("/api/v1/users/{$user->id}")->assertOk();

        $this->assertSame(ClientMembership::ESTADO_CANCELADA, $membership->fresh()->getAttribute('estado'));
    }

    public function test_charging_again_after_annulling_applies_the_discount_only_once(): void
    {
        Notification::fake();
        Storage::fake('receipts');
        [, $client] = $this->member(descuentoPct: 10);
        $admin = $this->userWithRole('administrador');
        $appointment = $this->appointmentFor($client);
        $charge = ['appointment_id' => (string) $appointment->id, 'metodo_pago' => 'efectivo'];

        $first = app(PaymentService::class)->create($charge, (string) $admin->id);
        $this->assertEquals(180.0, (float) $first->monto);

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/payments/{$first->id}", ['motivo' => 'Se cobró con el método equivocado'])
            ->assertOk();

        $second = app(PaymentService::class)->create($charge, (string) $admin->id);

        $this->assertEquals(180.0, (float) $second->monto);
    }

    public function test_the_price_is_cleared_before_the_appointment_can_be_charged_again(): void
    {
        [, $client] = $this->member();
        $admin = $this->userWithRole('administrador');
        $appointment = $this->appointmentFor($client);
        $appointment->update(['precio_cobrado' => 180]);
        $payment = Payment::create([
            'appointment_id' => (string) $appointment->id, 'monto' => 180, 'metodo_pago' => 'efectivo', 'propina' => 0,
            'estado' => Payment::ESTADO_VERIFICADO,
        ]);
        $seguiaBloqueada = null;
        Appointment::updating(function () use ($payment, &$seguiaBloqueada) {
            $seguiaBloqueada = Payment::find($payment->getKey()) !== null;
        });

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/payments/{$payment->getKey()}", ['motivo' => 'Se cobró con el método equivocado'])
            ->assertOk();

        $this->assertTrue($seguiaBloqueada, 'El precio debe limpiarse mientras el cobro aún bloquea la cita.');
    }

    public function test_annulling_a_deposit_keeps_the_charged_price(): void
    {
        [, $client] = $this->member();
        $admin = $this->userWithRole('administrador');
        $appointment = $this->appointmentFor($client);
        $appointment->update(['precio_cobrado' => 180]);
        $deposit = Payment::create([
            'appointment_id' => (string) $appointment->id, 'monto' => 90, 'metodo_pago' => 'tarjeta', 'propina' => 0,
            'estado' => Payment::ESTADO_VERIFICADO, 'es_deposito' => true,
        ]);

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/payments/{$deposit->id}", ['motivo' => 'Anticipo registrado dos veces'])
            ->assertOk();

        $this->assertEquals(180.0, (float) $appointment->fresh()->precio_cobrado);
    }
}
