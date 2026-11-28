<?php

namespace Tests\UAT;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPackage;
use App\Models\MembershipInvoice;
use App\Models\MembershipPlan;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\Membership\MembershipService;
use App\Services\Payment\CashCloseService;
use App\Services\Payment\StripePaymentService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Planes Club: la membresía incluye N usos mensuales de ciertos servicios, que se crean al
 * pagarse cada factura, se canjean al cobrar la cita y vencen al renovar.
 */
class MembershipClubApiTest extends TestCase
{
    private Service $corte;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();
        Storage::fake('receipts');
        $this->corte = Service::create(['nombre' => 'Corte Club', 'precio' => 180, 'duracion_min' => 30, 'activo' => true]);
        $this->admin = $this->userWithRole('administrador');
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        User::withTrashed()->forceDelete();

        foreach ([ClientPackage::class, MembershipInvoice::class, ClientMembership::class, MembershipPlan::class, Payment::class, Service::class, Barber::class, Client::class, MobileApiToken::class, Role::class, Permission::class] as $model) {
            $model::query()->delete();
        }

        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName).' Club', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        return $user;
    }

    private function actingWithToken(User $user): static
    {
        $plaintext = (string) Str::uuid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plaintext)]);

        return $this->withToken($plaintext);
    }

    private function clubPlan(): MembershipPlan
    {
        return MembershipPlan::create([
            'nombre' => 'Club Corte', 'precio_mensual' => 299, 'descuento_pct' => 10, 'activo' => true,
            'stripe_product_id' => 'prod_club', 'stripe_price_id' => 'price_club',
            'servicios_incluidos' => [['service_id' => (string) $this->corte->id, 'usos' => 2]],
        ]);
    }

    /** Contrata (Stripe simulado) y activa la membresía. @return array{0: User, 1: Client, 2: ClientMembership} */
    private function member(MembershipPlan $plan): array
    {
        $user = $this->userWithRole('cliente');
        $client = Client::create(['user_id' => (string) $user->id, 'telefono' => '5551230000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('createCustomer')->andReturn('cus_club');
            $mock->shouldReceive('createSubscription')->andReturn(['subscription_id' => 'sub_club', 'client_secret' => 'secret']);
            $mock->shouldReceive('cancelSubscriptionNow');
        });
        $membership = app(MembershipService::class)->subscribe($client, $plan)['membership'];
        $membership->update(['estado' => ClientMembership::ESTADO_ACTIVA, 'periodo_actual_fin' => now()->addMonth()]);

        return [$user, $client, $membership];
    }

    private function pay(string $invoiceId, ?Carbon $at = null): void
    {
        app(MembershipService::class)->recordSuccessfulInvoice('sub_club', $invoiceId, 299.0, $at ?? now());
    }

    private function appointmentFor(Client $client): Appointment
    {
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $this->corte->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'estado' => 'confirmada',
        ]);
    }

    private function remaining(Client $client): int
    {
        return (int) ClientPackage::where('client_id', (string) $client->id)->where('estado', ClientPackage::ESTADO_ACTIVO)->get()->sum('usos_restantes');
    }

    public function test_admin_creates_a_plan_with_included_services(): void
    {
        $this->mock(StripePaymentService::class, fn ($mock) => $mock->shouldReceive('createMonthlyPrice')->andReturn(['product_id' => 'p', 'price_id' => 'r']));
        $data = ['nombre' => 'Club Corte', 'precio_mensual' => 299, 'descuento_pct' => 10];

        $this->actingWithToken($this->admin)->postJson('/api/v1/admin/membership-plans', $data + ['servicios_incluidos' => [['service_id' => (string) $this->corte->id, 'usos' => 2]]])
            ->assertCreated()
            ->assertJsonPath('data.servicios_incluidos.0.nombre', 'Corte Club')
            ->assertJsonPath('data.servicios_incluidos.0.usos', 2)
            ->assertJsonPath('data.valor_lista', 360);

        foreach ([['service_id' => 'no-existe', 'usos' => 2], ['service_id' => (string) $this->corte->id, 'usos' => 40]] as $invalido) {
            $this->actingWithToken($this->admin)->postJson('/api/v1/admin/membership-plans', $data + ['servicios_incluidos' => [$invalido]])->assertStatus(422);
        }
    }

    public function test_editing_a_plan_does_not_change_what_was_sold(): void
    {
        $plan = $this->clubPlan();
        [, $client] = $this->member($plan);
        $plan->update(['descuento_pct' => 1, 'servicios_incluidos' => []]);

        $this->assertSame(10, app(MembershipService::class)->activeDiscountFor($client));
        $this->pay('in_1');
        $this->assertSame(2, $this->remaining($client));
    }

    public function test_each_paid_invoice_grants_the_uses_once_and_renewal_expires_the_old_ones(): void
    {
        [, $client] = $this->member($this->clubPlan());

        $this->pay('in_1');
        $this->pay('in_1');
        $this->assertSame(2, $this->remaining($client));

        $this->pay('in_2', now()->addMonth());
        $this->assertSame(2, $this->remaining($client));
        $this->assertSame(1, ClientPackage::where('estado', ClientPackage::ESTADO_EXPIRADO)->count());
    }

    public function test_charging_with_an_included_use_costs_nothing_and_annulling_returns_it(): void
    {
        [, $client] = $this->member($this->clubPlan());
        $this->pay('in_1');
        $appointment = $this->appointmentFor($client);

        $disponible = $this->actingWithToken($this->admin)->getJson('/api/v1/appointments/chargeable')
            ->assertOk()->assertJsonPath('data.0.paquete_disponible.origen', 'membresia')->json('data.0.paquete_disponible.id');

        $this->actingWithToken($this->admin)->postJson('/api/v1/payments', [
            'appointment_id' => (string) $appointment->id, 'monto' => 0, 'metodo_pago' => 'efectivo', 'usar_paquete_id' => $disponible,
        ])->assertSuccessful();
        $this->assertSame(1, $this->remaining($client));

        $payment = Payment::where('appointment_id', (string) $appointment->id)->firstOrFail();
        $this->assertSame(0.0, (float) $payment->getAttribute('monto'));
        $this->actingWithToken($this->admin)->deleteJson("/api/v1/payments/{$payment->getKey()}", ['motivo' => 'Se cobró la cita equivocada'])->assertOk();
        $this->assertSame(2, $this->remaining($client));
    }

    public function test_client_sees_what_a_plan_includes_and_the_remaining_uses(): void
    {
        [$user] = $this->member($this->clubPlan());
        $this->pay('in_1');

        $this->actingWithToken($user)->getJson('/api/v1/memberships/plans')->assertOk()
            ->assertJsonPath('data.0.servicios_incluidos.0.nombre', 'Corte Club')
            ->assertJsonPath('data.0.valor_lista', 360);

        $this->actingWithToken($user)->getJson('/api/v1/memberships/mine')->assertOk()
            ->assertJsonPath('data.usos.0.nombre', 'Corte Club')
            ->assertJsonPath('data.usos.0.restantes', 2)
            ->assertJsonPath('data.usos.0.totales', 2);
    }

    public function test_an_included_service_is_not_charged_online(): void
    {
        [$user, $client] = $this->member($this->clubPlan());
        $this->pay('in_1');
        $appointment = $this->appointmentFor($client);

        $this->actingWithToken($user)->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $appointment->id])
            ->assertStatus(422)->assertJsonPath('incluido_en_membresia', true);
    }

    public function test_booking_with_pay_now_asks_no_payment_for_an_included_service(): void
    {
        [, $client] = $this->member($this->clubPlan());
        $this->pay('in_1');
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);

        $this->actingWithToken($this->admin)->postJson('/api/v1/appointments', [
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $this->corte->id,
            'fecha' => now()->addDays(2)->toDateString(), 'hora_inicio' => '15:00', 'pagar_ahora' => true,
        ])->assertCreated()
            ->assertJsonPath('data.deposito_requerido', false)
            ->assertJsonPath('incluido_en_membresia', true);

        $this->assertSame(2, $this->remaining($client));
    }

    public function test_membership_uses_are_not_counted_as_package_sales(): void
    {
        $this->member($this->clubPlan());
        $this->pay('in_1');

        $corte = app(CashCloseService::class)->expectedFor(now());

        $this->assertSame(0, $corte['paquetes']);
        $this->assertSame(1, $corte['membresias']);
    }

    public function test_deleting_the_account_expires_the_remaining_uses(): void
    {
        [$user, $client] = $this->member($this->clubPlan());
        $this->pay('in_1');

        $this->actingWithToken($user)->deleteJson('/api/v1/profile', ['password' => 'password'])->assertOk();

        $this->assertSame(0, $this->remaining($client));
    }
}
