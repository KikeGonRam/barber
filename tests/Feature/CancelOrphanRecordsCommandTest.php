<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\User;
use App\Models\Waitlist;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * data:cancel-orphans: sin --apply no cambia nada; con --apply cancela solo lo activo que apunta
 * a un cliente o barbero inexistente, y nunca toca citas válidas ni ya terminadas.
 */
class CancelOrphanRecordsCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        // forceDelete(): un delete() masivo deja el registro soft-deleted con
        // bloquea_horario en true y choca con appointments_active_slot_unique.
        Appointment::withTrashed()->forceDelete();
        Waitlist::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        User::withTrashed()->forceDelete();

        parent::tearDown();
    }

    private function realPair(): array
    {
        $clientUser = User::create(['name' => 'Cliente Real', 'email' => 'cliente-real-'.uniqid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5550001111', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $barberUser = User::create(['name' => 'Barbero Real', 'email' => 'barbero-real-'.uniqid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Real', 'activo' => true]);

        return [$client, $barber];
    }

    private function appointment(string $estado, string $clientId, string $barberId, string $hora): Appointment
    {
        return Appointment::create([
            'client_id' => $clientId,
            'barber_id' => $barberId,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => $hora,
            'hora_fin' => '23:59:00',
            'estado' => $estado,
        ]);
    }

    public function test_without_apply_it_only_reports_and_changes_nothing(): void
    {
        $orphan = $this->appointment('confirmada', (string) Str::uuid(), (string) Str::uuid(), '09:00:00');

        $this->artisan('data:cancel-orphans')->assertSuccessful();

        $this->assertSame('confirmada', $orphan->fresh()->estado);
    }

    public function test_apply_cancels_only_active_records_that_point_to_missing_people(): void
    {
        [$client, $barber] = $this->realPair();
        $orphan = $this->appointment('confirmada', (string) Str::uuid(), (string) Str::uuid(), '09:00:00');
        $valid = $this->appointment('pendiente', (string) $client->id, (string) $barber->id, '10:00:00');
        $finished = $this->appointment('completada', (string) Str::uuid(), (string) $barber->id, '11:00:00');
        $orphanWait = Waitlist::create(['client_id' => (string) Str::uuid(), 'barber_id' => (string) $barber->id, 'service_id' => (string) Str::uuid(), 'fecha' => now()->addDays(5), 'estado' => Waitlist::ESTADO_ACTIVO]);
        $validWait = Waitlist::create(['client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) Str::uuid(), 'fecha' => now()->addDays(5), 'estado' => Waitlist::ESTADO_ACTIVO]);

        $this->artisan('data:cancel-orphans', ['--apply' => true])->assertSuccessful();

        $this->assertSame('cancelada', $orphan->fresh()->estado);
        $this->assertNotNull($orphan->fresh()->cancelada_en);
        $this->assertSame('pendiente', $valid->fresh()->estado);
        $this->assertSame('completada', $finished->fresh()->estado);
        $this->assertSame(Waitlist::ESTADO_CANCELADO, $orphanWait->fresh()?->getAttribute('estado'));
        $this->assertSame(Waitlist::ESTADO_ACTIVO, $validWait->fresh()?->getAttribute('estado'));
    }
}
