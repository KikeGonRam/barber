<?php

namespace Tests\UAT;

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
 * CP-035: el cliente debe poder pagar una cita aprobada que reservó en efectivo, y la API
 * dice la verdad sobre el estado del pago (payment_status / can_change_payment_method).
 */
class AppointmentPaymentStatusApiTest extends TestCase
{
    private string $clientToken;

    private Client $client;

    private Barber $barber;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente Pago', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $this->clientToken = 'tok-'.Str::uuid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 't', 'token_hash' => hash('sha256', $this->clientToken)]);
        $this->client = Client::create(['user_id' => (string) $user->id, 'telefono' => '5551234567']);

        $barberUser = User::create(['name' => 'Barbero Pago', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $this->barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Pago', 'activo' => true]);
        $this->service = Service::create(['nombre' => 'Corte', 'categoria' => 'corte', 'precio' => 100, 'duracion_min' => 30, 'activo' => true]);
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

    private function cita(string $estado, string $hora = '10:00'): Appointment
    {
        return Appointment::create([
            'client_id' => (string) $this->client->id,
            'barber_id' => (string) $this->barber->id,
            'service_id' => (string) $this->service->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => $hora.':00',
            'hora_fin' => $hora.':30',
            'estado' => $estado,
        ]);
    }

    private function pago(Appointment $cita, string $estado, bool $deposito = false): Payment
    {
        return Payment::create([
            'appointment_id' => (string) $cita->id,
            'monto' => 100,
            'metodo_pago' => 'transferencia',
            'estado' => $estado,
            'es_deposito' => $deposito,
        ]);
    }

    private function fila(Appointment $cita): array
    {
        $res = $this->withHeader('Authorization', "Bearer {$this->clientToken}")->getJson('/api/v1/appointments');
        $res->assertOk();

        return collect($res->json('data'))->firstWhere('id', (string) $cita->id);
    }

    public function test_cita_pendiente_aun_no_es_cobrable(): void
    {
        $row = $this->fila($this->cita('pendiente'));

        $this->assertSame('sin_pago', $row['payment_status']);
        $this->assertFalse($row['is_chargeable']);
        $this->assertTrue($row['can_change_payment_method']);
    }

    public function test_confirmada_en_efectivo_sin_pagos_queda_pendiente_en_recepcion_y_pagable(): void
    {
        $row = $this->fila($this->cita('confirmada'));

        $this->assertSame('pendiente_en_recepcion', $row['payment_status']);
        $this->assertTrue($row['is_chargeable']);
        $this->assertFalse($row['has_payment']);
        $this->assertTrue($row['can_change_payment_method']);
    }

    public function test_transferencia_en_revision_no_permite_cambiar_de_metodo(): void
    {
        $cita = $this->cita('confirmada');
        $this->pago($cita, Payment::ESTADO_PENDIENTE_VERIFICACION);

        $row = $this->fila($cita);

        $this->assertSame('transferencia_en_revision', $row['payment_status']);
        $this->assertTrue($row['has_payment']);
        $this->assertFalse($row['can_change_payment_method']);
    }

    public function test_pago_cobrado_marca_pagado_y_bloquea_el_cambio(): void
    {
        $cita = $this->cita('confirmada');
        $this->pago($cita, Payment::ESTADO_VERIFICADO);

        $row = $this->fila($cita);

        $this->assertSame('pagado', $row['payment_status']);
        $this->assertFalse($row['can_change_payment_method']);
    }

    public function test_pago_rechazado_no_esconde_el_pago_de_la_cita(): void
    {
        $cita = $this->cita('confirmada');
        $this->pago($cita, Payment::ESTADO_RECHAZADO);

        $row = $this->fila($cita);

        $this->assertSame('pendiente_en_recepcion', $row['payment_status']);
        $this->assertFalse($row['has_payment']);
        $this->assertTrue($row['can_change_payment_method']);
    }

    public function test_citas_terminales_no_permiten_cambiar_de_metodo(): void
    {
        $horas = ['cancelada' => '09:00', 'completada' => '11:00', 'no_asistio' => '12:00'];
        foreach ($horas as $estado => $hora) {
            $row = $this->fila($this->cita($estado, $hora));
            $this->assertFalse($row['can_change_payment_method'], $estado);
        }
    }

    public function test_stripe_intent_rechaza_con_transferencia_en_revision(): void
    {
        $cita = $this->cita('confirmada');
        $this->pago($cita, Payment::ESTADO_PENDIENTE_VERIFICACION);

        $res = $this->withHeader('Authorization', "Bearer {$this->clientToken}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $cita->id]);

        $res->assertStatus(422);
        $this->assertSame('La cita ya tiene un pago registrado.', $res->json('message'));
    }

    public function test_stripe_intent_no_se_bloquea_por_un_pago_rechazado(): void
    {
        $cita = $this->cita('confirmada');
        $this->pago($cita, Payment::ESTADO_RECHAZADO);

        $res = $this->withHeader('Authorization', "Bearer {$this->clientToken}")
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $cita->id]);

        $this->assertNotSame('La cita ya tiene un pago registrado.', $res->json('message'));
    }

    public function test_cliente_ajeno_no_puede_pagar_la_cita(): void
    {
        $cita = $this->cita('confirmada');
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $otro = User::create(['name' => 'Otro', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $otro->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        Client::create(['user_id' => (string) $otro->id, 'telefono' => '5550000000']);
        MobileApiToken::create(['user_id' => (string) $otro->id, 'name' => 't', 'token_hash' => hash('sha256', 'tok-otro')]);

        $this->withHeader('Authorization', 'Bearer tok-otro')
            ->postJson('/api/v1/payments/stripe-intent', ['appointment_id' => (string) $cita->id])
            ->assertStatus(403);
    }
}
