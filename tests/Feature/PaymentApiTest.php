<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\GiftCard;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Package\GiftCardService;
use App\Services\Payment\StripePaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre el "Centro de
 * Facturación" del frontend Nuxt (ver frontend-urban/.claude/skills/
 * nuxt-migration-plan/SKILL.md, Fase 9.3): historial paginado con filtros +
 * estadísticas (GET /api/v1/payments), y el flujo de revisión de
 * comprobantes de transferencia (pending/approve/reject), que antes solo
 * existía como vistas Blade con sesión.
 */
class PaymentApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Payment::query()->delete();
        GiftCard::query()->delete();
        Appointment::withTrashed()->forceDelete();
        Service::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function tokenFor(User $user, string $plaintext): string
    {
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
    }

    private function staffUser(string $roleName, string $email): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName).' Payments', 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        return $user;
    }

    public function test_recepcionista_gets_payment_history_with_stats_and_pending_count(): void
    {
        $user = $this->staffUser('recepcionista', 'recepcion-payments@test.local');

        $barberUser = User::create(['name' => 'Barbero Payments', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Payments', 'activo' => true]);
        $clientUser = User::create(['name' => 'Cliente Payments', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 1]);
        $service = Service::create(['nombre' => 'Corte Payments Test', 'categoria' => 'corte', 'precio' => 150, 'duracion_min' => 30, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->toDateString(), 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'completada',
        ]);

        // Segunda cita distinta para el segundo pago: el indice unico parcial
        // de Fase 4 (payments_active_appointment_unique) ahora impide dos
        // pagos activos (no rechazados) sobre la MISMA cita a nivel de base
        // de datos -- ver Payment::booted()/la migracion del indice.
        $appointmentB = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->toDateString(), 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'estado' => 'completada',
        ]);

        Payment::create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 150, 'metodo_pago' => 'efectivo', 'propina' => 20,
            'created_by' => (string) $user->id, 'estado' => Payment::ESTADO_VERIFICADO,
        ]);

        Payment::create([
            'appointment_id' => (string) $appointmentB->id,
            'monto' => 100, 'metodo_pago' => 'transferencia', 'propina' => 0,
            'created_by' => (string) $user->id, 'estado' => Payment::ESTADO_PENDIENTE_VERIFICACION,
            'comprobante_cliente' => 'comprobantes-transferencia/test.jpg',
        ]);

        $token = $this->tokenFor($user, 'test-plaintext-token-payments-index');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/payments');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => ['*' => ['id', 'monto', 'metodo_pago', 'propina', 'receipt_url', 'created_at', 'appointment', 'creator']],
            'meta' => ['current_page', 'last_page', 'total', 'stats' => ['total_hoy', 'total_mes', 'count', 'metodos'], 'pending_count'],
        ]);
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('meta.stats.count', 2);
        $response->assertJsonPath('meta.pending_count', 1);

        // Filtro por método de pago: solo debe traer el pago en efectivo.
        $filtered = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/payments?metodo_pago=efectivo');
        $filtered->assertOk();
        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.metodo_pago', 'efectivo');
    }

    public function test_cliente_cannot_access_payment_history(): void
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente Payments Guard', 'email' => 'cliente-payments-guard@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $token = $this->tokenFor($user, 'test-plaintext-token-payments-guard');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/payments');

        $response->assertForbidden();
    }

    public function test_admin_can_list_approve_and_reject_pending_transfers(): void
    {
        $user = $this->staffUser('administrador', 'admin-payments@test.local');

        $barberUser = User::create(['name' => 'Barbero Transfer', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Transfer', 'activo' => true]);
        $clientUser = User::create(['name' => 'Cliente Transfer', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5559876543', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $service = Service::create(['nombre' => 'Corte Transfer Test', 'categoria' => 'corte', 'precio' => 180, 'duracion_min' => 30, 'activo' => true]);

        $appointmentToApprove = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->toDateString(), 'hora_inicio' => '09:00:00', 'hora_fin' => '09:30:00', 'estado' => 'confirmada',
        ]);
        $appointmentToReject = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->toDateString(), 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'confirmada',
        ]);

        $paymentToApprove = Payment::create([
            'appointment_id' => (string) $appointmentToApprove->id,
            'monto' => 180, 'metodo_pago' => 'transferencia', 'propina' => 0,
            'created_by' => (string) $clientUser->id, 'estado' => Payment::ESTADO_PENDIENTE_VERIFICACION,
            'comprobante_cliente' => 'comprobantes-transferencia/approve.jpg',
        ]);
        $paymentToReject = Payment::create([
            'appointment_id' => (string) $appointmentToReject->id,
            'monto' => 180, 'metodo_pago' => 'transferencia', 'propina' => 0,
            'created_by' => (string) $clientUser->id, 'estado' => Payment::ESTADO_PENDIENTE_VERIFICACION,
            'comprobante_cliente' => 'comprobantes-transferencia/reject.jpg',
        ]);

        $token = $this->tokenFor($user, 'test-plaintext-token-payments-transfer');

        $pendingList = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/payments/pending');
        $pendingList->assertOk();
        $pendingList->assertJsonCount(2, 'data');
        $pendingList->assertJsonStructure([
            'data' => ['*' => ['id', 'monto', 'created_at', 'comprobante_url', 'ocr_texto', 'ocr_monto_detectado', 'appointment' => ['id', 'client', 'service', 'service_price']]],
        ]);

        $approve = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/payments/{$paymentToApprove->id}/approve");
        $approve->assertOk();
        $approve->assertJsonPath('data.estado', Payment::ESTADO_VERIFICADO);
        $this->assertSame('completada', $appointmentToApprove->fresh()->estado);

        $reject = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/payments/{$paymentToReject->id}/reject", ['motivo_rechazo' => 'Monto no coincide con el servicio.']);
        $reject->assertOk();
        $reject->assertJsonPath('data.estado', Payment::ESTADO_RECHAZADO);
        $this->assertSame('confirmada', $appointmentToReject->fresh()->estado);

        // Un comprobante ya revisado no puede volver a aprobarse.
        $doubleApprove = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/payments/{$paymentToApprove->id}/approve");
        $doubleApprove->assertStatus(422);
    }

    /**
     * Cubre stripeIntent() (Fase A del plan Stripe, cero cobertura antes de
     * esta fase): el monto SIEMPRE se recalcula en servidor (precio del
     * servicio -> descuento de nivel -> puntos canjeados), nunca se recibe
     * del cliente -- el endpoint ni siquiera acepta un campo "monto" en el
     * payload. Se mockea StripePaymentService para no pegarle a la API real
     * de Stripe.
     */
    public function test_stripe_intent_is_staff_only(): void
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente Stripe Guard', 'email' => 'cliente-stripe-guard@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $token = $this->tokenFor($user, 'test-plaintext-token-stripe-guard');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => 'irrelevante']);

        $response->assertForbidden();
    }

    public function test_stripe_intent_computes_the_amount_server_side_with_loyalty_discount(): void
    {
        $user = $this->staffUser('recepcionista', 'recepcion-stripe@test.local');

        $barberUser = User::create(['name' => 'Barbero Stripe Intent', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Stripe Intent', 'activo' => true]);
        $clientUser = User::create(['name' => 'Cliente Stripe Intent', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        // Nivel 'vip' para que el descuento de lealtad sea distinto de cero
        // y así confirmar que sí se aplica antes de mandarle el monto a Stripe.
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5551112222', 'nivel' => 'vip', 'puntos' => 0]);
        $service = Service::create(['nombre' => 'Corte Stripe Intent', 'precio' => 300, 'duracion_min' => 30, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '09:00:00', 'hora_fin' => '09:30:00', 'estado' => 'confirmada',
        ]);

        $expectedAmount = LoyaltyService::applyDiscount(300.0, 'vip');

        $this->mock(StripePaymentService::class, function ($mock) use ($expectedAmount, $appointment) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->withArgs(fn ($amount, $currency, $metadata) => abs($amount - $expectedAmount) < 0.01
                    && $currency === 'mxn'
                    && $metadata['appointment_id'] === (string) $appointment->id
                )
                ->andReturn(['client_secret' => 'pi_test_secret_123', 'payment_intent_id' => 'pi_test_123']);
        });

        $token = $this->tokenFor($user, 'test-plaintext-token-stripe-intent');

        // El cliente manda un "monto" absurdo a propósito -- el endpoint no
        // tiene ni siquiera un campo para eso, así que no puede afectar nada.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', [
                'appointment_id' => (string) $appointment->id,
                'monto' => 1,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.client_secret', 'pi_test_secret_123');
    }

    public function test_stripe_intent_adds_tip_on_top_of_the_computed_amount(): void
    {
        $user = $this->staffUser('recepcionista', 'recepcion-stripe-propina@test.local');

        $barberUser = User::create(['name' => 'Barbero Stripe Propina', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Stripe Propina', 'activo' => true]);
        $clientUser = User::create(['name' => 'Cliente Stripe Propina', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5550001111', 'nivel' => 'nuevo', 'puntos' => 0]);
        $service = Service::create(['nombre' => 'Corte Stripe Propina', 'precio' => 300, 'duracion_min' => 30, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '09:00:00', 'hora_fin' => '09:30:00', 'estado' => 'confirmada',
        ]);

        // 300 (precio, sin descuento de nivel 'nuevo') + 45 de propina = 345
        // que Stripe realmente debe cobrar; los metadatos deben cargar la
        // propina aparte para que el webhook la registre en su propio campo
        // (nunca mezclada con precio_cobrado).
        $this->mock(StripePaymentService::class, function ($mock) use ($appointment) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->withArgs(fn ($amount, $currency, $metadata) => abs($amount - 345.0) < 0.01
                    && $currency === 'mxn'
                    && $metadata['appointment_id'] === (string) $appointment->id
                    && $metadata['propina'] === '45'
                )
                ->andReturn(['client_secret' => 'pi_propina_secret', 'payment_intent_id' => 'pi_propina']);
        });

        $token = $this->tokenFor($user, 'test-plaintext-token-stripe-propina');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', [
                'appointment_id' => (string) $appointment->id,
                'propina' => 45,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.client_secret', 'pi_propina_secret');
    }

    public function test_stripe_intent_rejects_redeeming_more_points_than_allowed(): void
    {
        $user = $this->staffUser('recepcionista', 'recepcion-stripe-puntos@test.local');

        $barberUser = User::create(['name' => 'Barbero Stripe Puntos', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Stripe Puntos', 'activo' => true]);
        $clientUser = User::create(['name' => 'Cliente Stripe Puntos', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5553334444', 'nivel' => 'nuevo', 'puntos' => 5]);
        $service = Service::create(['nombre' => 'Corte Stripe Puntos', 'precio' => 300, 'duracion_min' => 30, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '09:00:00', 'hora_fin' => '09:30:00', 'estado' => 'confirmada',
        ]);

        $token = $this->tokenFor($user, 'test-plaintext-token-stripe-puntos');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', [
                'appointment_id' => (string) $appointment->id,
                'puntos_canjeados' => 999999,
            ]);

        $response->assertStatus(422);
    }

    /**
     * Gift card + tarjeta: a diferencia del paquete/premio de rifa (cubren el
     * 100%), una gift card es parcial, así que sí puede combinarse con
     * tarjeta para el remanente. El saldo NUNCA se descuenta en stripeIntent()
     * -- solo se lee para calcular cuánto cobrarle a Stripe.
     */
    public function test_stripe_intent_reduces_the_amount_by_the_gift_card_balance(): void
    {
        $user = $this->staffUser('recepcionista', 'recepcion-stripe-gift@test.local');

        $barberUser = User::create(['name' => 'Barbero Stripe Gift', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Stripe Gift', 'activo' => true]);
        $clientUser = User::create(['name' => 'Cliente Stripe Gift', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5556667777', 'nivel' => 'nuevo', 'puntos' => 0]);
        $service = Service::create(['nombre' => 'Corte Stripe Gift', 'precio' => 300, 'duracion_min' => 30, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '09:00:00', 'hora_fin' => '09:30:00', 'estado' => 'confirmada',
        ]);

        $giftCard = app(GiftCardService::class)->purchaseCash(120, null, 'QA', null, (string) Str::uuid());

        $this->mock(StripePaymentService::class, function ($mock) use ($giftCard) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->withArgs(fn ($amount, $currency, $metadata) => abs($amount - 180.0) < 0.01
                    && $metadata['codigo_gift_card'] === $giftCard->code
                )
                ->andReturn(['client_secret' => 'pi_test_secret_gift', 'payment_intent_id' => 'pi_test_gift']);
        });

        $token = $this->tokenFor($user, 'test-plaintext-token-stripe-gift');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', [
                'appointment_id' => (string) $appointment->id,
                'codigo_gift_card' => $giftCard->code,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.client_secret', 'pi_test_secret_gift');

        // El saldo de la gift card no se tocó -- eso solo pasa cuando el
        // webhook confirme el pago.
        $this->assertEquals(120.0, (float) $giftCard->fresh()->saldo);
    }

    public function test_stripe_intent_rejects_a_gift_card_that_already_covers_the_full_amount(): void
    {
        $user = $this->staffUser('recepcionista', 'recepcion-stripe-gift-full@test.local');

        $barberUser = User::create(['name' => 'Barbero Stripe Gift Full', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Stripe Gift Full', 'activo' => true]);
        $clientUser = User::create(['name' => 'Cliente Stripe Gift Full', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5558889999', 'nivel' => 'nuevo', 'puntos' => 0]);
        $service = Service::create(['nombre' => 'Corte Stripe Gift Full', 'precio' => 300, 'duracion_min' => 30, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '09:00:00', 'hora_fin' => '09:30:00', 'estado' => 'confirmada',
        ]);

        $giftCard = app(GiftCardService::class)->purchaseCash(500, null, 'QA', null, (string) Str::uuid());

        $token = $this->tokenFor($user, 'test-plaintext-token-stripe-gift-full');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', [
                'appointment_id' => (string) $appointment->id,
                'codigo_gift_card' => $giftCard->code,
            ]);

        $response->assertStatus(422);
    }

    /**
     * Autopago del cliente (Fase B): cubre que un cliente autenticado pueda
     * pagar su PROPIA cita con tarjeta por el mismo endpoint que usa staff,
     * y que el monto se calcule server-side igual que en el flujo de staff.
     */
    public function test_stripe_intent_allows_client_to_pay_for_their_own_appointment(): void
    {
        $barberUser = User::create(['name' => 'Barbero Autopago', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Autopago', 'activo' => true]);
        $clientRole = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $clientUser = User::create(['name' => 'Cliente Autopago', 'email' => 'cliente-autopago@test.local', 'password' => 'password']);
        $clientUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $clientRole->id]])->save();
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5559998888', 'nivel' => 'nuevo', 'puntos' => 0]);
        $service = Service::create(['nombre' => 'Corte Autopago', 'precio' => 250, 'duracion_min' => 30, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'confirmada',
        ]);

        $this->mock(StripePaymentService::class, function ($mock) use ($appointment) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->withArgs(fn ($amount, $currency, $metadata) => abs($amount - 250.0) < 0.01
                    && $currency === 'mxn'
                    && $metadata['appointment_id'] === (string) $appointment->id
                )
                ->andReturn(['client_secret' => 'pi_autopago_secret', 'payment_intent_id' => 'pi_autopago']);
        });

        $token = $this->tokenFor($clientUser, 'test-plaintext-token-autopago');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $appointment->id]);

        $response->assertOk();
        $response->assertJsonPath('data.client_secret', 'pi_autopago_secret');
    }

    public function test_stripe_intent_rejects_client_paying_for_another_clients_appointment(): void
    {
        $barberUser = User::create(['name' => 'Barbero Ajeno', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Ajeno', 'activo' => true]);
        $ownerUser = User::create(['name' => 'Dueño Cita', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $owner = Client::create(['user_id' => (string) $ownerUser->id, 'telefono' => '5551110000', 'nivel' => 'nuevo', 'puntos' => 0]);
        $service = Service::create(['nombre' => 'Corte Ajeno', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'client_id' => (string) $owner->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'estado' => 'confirmada',
        ]);

        $clientRole = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $otroUser = User::create(['name' => 'Otro Cliente', 'email' => 'otro-cliente-autopago@test.local', 'password' => 'password']);
        $otroUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $clientRole->id]])->save();
        Client::create(['user_id' => (string) $otroUser->id, 'telefono' => '5552223333', 'nivel' => 'nuevo', 'puntos' => 0]);

        $token = $this->tokenFor($otroUser, 'test-plaintext-token-otro-cliente');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $appointment->id]);

        $response->assertForbidden();
    }

    public function test_stripe_intent_rejects_a_non_chargeable_appointment(): void
    {
        $user = $this->staffUser('recepcionista', 'recepcion-stripe-no-chargeable@test.local');

        $barberUser = User::create(['name' => 'Barbero No Chargeable', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero No Chargeable', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte No Chargeable', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '12:00:00', 'hora_fin' => '12:30:00', 'estado' => 'pendiente',
        ]);

        $token = $this->tokenFor($user, 'test-plaintext-token-no-chargeable');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $appointment->id]);

        $response->assertStatus(422);
    }

    public function test_stripe_intent_rejects_an_appointment_that_already_has_a_payment(): void
    {
        $user = $this->staffUser('recepcionista', 'recepcion-stripe-ya-pagada@test.local');

        $barberUser = User::create(['name' => 'Barbero Ya Pagada', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Ya Pagada', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte Ya Pagada', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '13:00:00', 'hora_fin' => '13:30:00', 'estado' => 'completada',
        ]);
        Payment::create([
            'appointment_id' => (string) $appointment->id, 'monto' => 200, 'metodo_pago' => 'efectivo',
            'estado' => Payment::ESTADO_VERIFICADO,
        ]);

        $token = $this->tokenFor($user, 'test-plaintext-token-ya-pagada');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $appointment->id]);

        $response->assertStatus(422);
    }

    /**
     * Cubre appointments/{appointment}/payment/receipt (autopago del cliente
     * por transferencia): deja el pago en revisión, nunca completa la cita
     * de inmediato -- eso solo pasa cuando recepción/admin aprueba (ver los
     * tests de approve() más arriba).
     */
    public function test_client_can_upload_transfer_receipt_for_their_own_chargeable_appointment(): void
    {
        Storage::fake('receipts');

        $barberUser = User::create(['name' => 'Barbero Recibo', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Recibo', 'activo' => true]);
        $clientRole = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $clientUser = User::create(['name' => 'Cliente Recibo', 'email' => 'cliente-recibo@test.local', 'password' => 'password']);
        $clientUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $clientRole->id]])->save();
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5554443333', 'nivel' => 'nuevo', 'puntos' => 0]);
        $service = Service::create(['nombre' => 'Corte Recibo', 'precio' => 280, 'duracion_min' => 30, 'activo' => true]);

        $appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '14:00:00', 'hora_fin' => '14:30:00', 'estado' => 'confirmada',
        ]);

        $token = $this->tokenFor($clientUser, 'test-plaintext-token-recibo');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->post(
            "/api/v1/appointments/{$appointment->code}/payment/receipt",
            [
                'comprobante' => UploadedFile::fake()->create('comprobante.jpg', 10, 'image/jpeg'),
                'propina' => 20,
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('data.estado', Payment::ESTADO_PENDIENTE_VERIFICACION);

        $payment = Payment::where('appointment_id', (string) $appointment->id)->firstOrFail();
        $this->assertSame(20.0, (float) $payment->propina);
        // El pago queda en revisión: la cita NO se completa todavía.
        $this->assertSame('confirmada', $appointment->fresh()->estado);
    }

    public function test_upload_receipt_rejects_a_client_who_does_not_own_the_appointment(): void
    {
        Storage::fake('receipts');

        $barberUser = User::create(['name' => 'Barbero Recibo Ajeno', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Recibo Ajeno', 'activo' => true]);
        $ownerUser = User::create(['name' => 'Dueño Recibo', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $owner = Client::create(['user_id' => (string) $ownerUser->id, 'telefono' => '5550009999', 'nivel' => 'nuevo', 'puntos' => 0]);
        $service = Service::create(['nombre' => 'Corte Recibo Ajeno', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'client_id' => (string) $owner->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '15:00:00', 'hora_fin' => '15:30:00', 'estado' => 'confirmada',
        ]);

        $clientRole = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $otroUser = User::create(['name' => 'Otro Cliente Recibo', 'email' => 'otro-cliente-recibo@test.local', 'password' => 'password']);
        $otroUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $clientRole->id]])->save();
        Client::create(['user_id' => (string) $otroUser->id, 'telefono' => '5551112222', 'nivel' => 'nuevo', 'puntos' => 0]);

        $token = $this->tokenFor($otroUser, 'test-plaintext-token-otro-recibo');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->post(
            "/api/v1/appointments/{$appointment->code}/payment/receipt",
            ['comprobante' => UploadedFile::fake()->create('comprobante.jpg', 10, 'image/jpeg')]
        );

        $response->assertForbidden();
    }

    /** Cliente con rol, perfil de cliente y token; opcionalmente con Customer de Stripe ya creado. */
    private function clientWithToken(string $tokenPlain, ?string $stripeCustomerId = null): array
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente Tarjetas', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $client = Client::create([
            'user_id' => (string) $user->id, 'telefono' => '5550001234', 'nivel' => 'nuevo', 'puntos' => 0,
            'stripe_customer_id' => $stripeCustomerId,
        ]);

        return [$user, $client, $this->tokenFor($user, $tokenPlain)];
    }

    private function chargeableAppointmentFor(Client $client): Appointment
    {
        $barberUser = User::create(['name' => 'Barbero Tarjetas', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Tarjetas', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte Tarjetas', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'estado' => 'confirmada',
        ]);
    }

    public function test_transfer_info_gives_the_bank_data_to_an_authenticated_client(): void
    {
        BarbershopSetting::query()->delete();
        BarbershopSetting::create([
            'nombre' => 'UrbanBlade Test',
            'datos_bancarios' => ['clabe' => '012345678901234567', 'banco' => 'BBVA', 'beneficiario' => 'UrbanBlade SA', 'concepto' => 'Cita UrbanBlade'],
        ]);
        [, , $token] = $this->clientWithToken('test-plaintext-token-transfer-info');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/payments/transfer-info');

        $response->assertOk()
            ->assertJsonPath('data.configurado', true)
            ->assertJsonPath('data.clabe', '012345678901234567')
            ->assertJsonPath('data.banco', 'BBVA')
            ->assertJsonPath('data.beneficiario', 'UrbanBlade SA')
            ->assertJsonPath('data.concepto', 'Cita UrbanBlade');

        BarbershopSetting::query()->delete();
    }

    public function test_transfer_info_says_not_configured_when_the_admin_has_not_set_a_clabe(): void
    {
        BarbershopSetting::query()->delete();
        [, , $token] = $this->clientWithToken('test-plaintext-token-transfer-info-empty');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/payments/transfer-info')
            ->assertOk()
            ->assertJsonPath('data.configurado', false)
            ->assertJsonPath('data.clabe', null);
    }

    public function test_transfer_info_requires_authentication(): void
    {
        $this->getJson('/api/v1/payments/transfer-info')->assertUnauthorized();
    }

    public function test_saved_cards_is_empty_when_the_client_has_no_stripe_customer(): void
    {
        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldNotReceive('savedCards');
        });
        [, , $token] = $this->clientWithToken('test-plaintext-token-cards-empty');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/payments/cards')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_saved_cards_lists_only_safe_data_of_the_clients_own_customer(): void
    {
        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('savedCards')->once()->with('cus_test_123')
                ->andReturn([['id' => 'pm_1', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2032]]);
        });
        [, , $token] = $this->clientWithToken('test-plaintext-token-cards-list', 'cus_test_123');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/payments/cards')
            ->assertOk()
            ->assertJsonPath('data.0.last4', '4242')
            ->assertJsonPath('data.0.brand', 'visa');
    }

    public function test_saved_cards_falls_back_to_an_empty_list_when_stripe_fails(): void
    {
        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('savedCards')->once()->andThrow(new \RuntimeException('Stripe caido'));
        });
        [, , $token] = $this->clientWithToken('test-plaintext-token-cards-fail', 'cus_test_456');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/payments/cards')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_saved_cards_are_not_available_to_staff(): void
    {
        $user = $this->staffUser('recepcionista', 'recepcion-cards@test.local');
        $token = $this->tokenFor($user, 'test-plaintext-token-cards-staff');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/payments/cards')->assertForbidden();
    }

    public function test_stripe_intent_attaches_the_customer_and_saves_the_card_when_the_client_asks_for_it(): void
    {
        [, $client, $token] = $this->clientWithToken('test-plaintext-token-intent-save');
        $appointment = $this->chargeableAppointmentFor($client);

        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('customerFor')->once()->andReturn('cus_new_1');
            $mock->shouldReceive('createPaymentIntent')->once()
                ->withArgs(fn ($amount, $currency, $metadata, $customerId, $saveCard) => abs($amount - 200) < 0.01
                    && $customerId === 'cus_new_1' && $saveCard === true)
                ->andReturn(['client_secret' => 'pi_secret_save', 'payment_intent_id' => 'pi_save']);
        });

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $appointment->id, 'guardar_tarjeta' => true])
            ->assertOk()
            ->assertJsonPath('data.client_secret', 'pi_secret_save');
    }

    public function test_stripe_intent_with_a_saved_card_uses_the_customer_but_does_not_save_another(): void
    {
        [, $client, $token] = $this->clientWithToken('test-plaintext-token-intent-saved', 'cus_existing');
        $appointment = $this->chargeableAppointmentFor($client);

        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldReceive('customerFor')->once()->andReturn('cus_existing');
            $mock->shouldReceive('createPaymentIntent')->once()
                ->withArgs(fn ($amount, $currency, $metadata, $customerId, $saveCard) => $customerId === 'cus_existing' && $saveCard === false)
                ->andReturn(['client_secret' => 'pi_secret_saved', 'payment_intent_id' => 'pi_saved']);
        });

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $appointment->id, 'tarjeta_guardada' => true])
            ->assertOk();
    }

    public function test_stripe_intent_without_card_flags_does_not_touch_the_stripe_customer(): void
    {
        [, $client, $token] = $this->clientWithToken('test-plaintext-token-intent-plain');
        $appointment = $this->chargeableAppointmentFor($client);

        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldNotReceive('customerFor');
            $mock->shouldReceive('createPaymentIntent')->once()
                ->withArgs(fn ($amount, $currency, $metadata, $customerId, $saveCard) => $customerId === null && $saveCard === false)
                ->andReturn(['client_secret' => 'pi_secret_plain', 'payment_intent_id' => 'pi_plain']);
        });

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $appointment->id])
            ->assertOk();
    }

    public function test_stripe_intent_ignores_the_save_card_flags_when_staff_charges(): void
    {
        $user = $this->staffUser('recepcionista', 'recepcion-guardar@test.local');
        $token = $this->tokenFor($user, 'test-plaintext-token-intent-staff-flags');
        [, $client] = $this->clientWithToken('test-plaintext-token-intent-staff-owner');
        $appointment = $this->chargeableAppointmentFor($client);

        $this->mock(StripePaymentService::class, function ($mock) {
            $mock->shouldNotReceive('customerFor');
            $mock->shouldReceive('createPaymentIntent')->once()
                ->withArgs(fn ($amount, $currency, $metadata, $customerId, $saveCard) => $customerId === null && $saveCard === false)
                ->andReturn(['client_secret' => 'pi_secret_staff', 'payment_intent_id' => 'pi_staff']);
        });

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $appointment->id, 'guardar_tarjeta' => true])
            ->assertOk();
    }
}
