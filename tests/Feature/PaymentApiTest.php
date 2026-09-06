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
use Database\Seeders\RolePermissionSeeder;
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

        Payment::create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 150, 'metodo_pago' => 'efectivo', 'propina' => 20,
            'created_by' => (string) $user->id, 'estado' => Payment::ESTADO_VERIFICADO,
        ]);

        Payment::create([
            'appointment_id' => (string) $appointment->id,
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
}
