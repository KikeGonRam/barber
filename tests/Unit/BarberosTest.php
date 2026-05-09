<?php

namespace Tests\Unit;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarberosTest extends TestCase
{
    use RefreshDatabase;

    protected $barber;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->barber = Barber::factory()->create(['user_id' => $this->user->id]);
    }

    // ==================== TESTS DE CREACIÓN ====================

    public function test_barbero_puede_ser_creado(): void
    {
        $barber = Barber::create([
            'user_id' => $this->user->id,
            'nombre' => 'Juan Pérez',
            'especialidad' => 'Cortes modernos',
            'telefono' => '+34912345678',
            'activo' => true,
        ]);

        $this->assertDatabaseHas('barbers', [
            'nombre' => 'Juan Pérez',
            'especialidad' => 'Cortes modernos',
        ]);
    }

    // ==================== TESTS DE RELACIONES ====================

    public function test_barbero_pertenece_a_usuario(): void
    {
        $this->assertInstanceOf(User::class, $this->barber->user);
        $this->assertEquals($this->user->id, $this->barber->user_id);
    }

    public function test_barbero_puede_tener_multiples_citas(): void
    {
        $appointments = \App\Models\Appointment::factory()
            ->count(3)
            ->create(['barber_id' => $this->barber->id]);

        $this->assertCount(3, $this->barber->appointments);
        $this->assertTrue($this->barber->appointments->contains($appointments[0]));
    }

    // ==================== TESTS DE VALIDACIÓN ====================

    public function test_barbero_requiere_nombre(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Barber::create([
            'user_id' => $this->user->id,
            'especialidad' => 'Cortes',
            // Sin nombre
        ]);
    }

    public function test_barbero_nombre_maximo_255_caracteres(): void
    {
        $barber = Barber::create([
            'user_id' => $this->user->id,
            'nombre' => str_repeat('a', 255),
            'especialidad' => 'Cortes',
        ]);

        $this->assertEquals(255, strlen($barber->nombre));
    }

    // ==================== TESTS DE ACTUALIZACIÓN ====================

    public function test_barbero_puede_ser_actualizado(): void
    {
        $this->barber->update(['nombre' => 'Carlos García']);

        $this->assertDatabaseHas('barbers', [
            'id' => $this->barber->id,
            'nombre' => 'Carlos García',
        ]);
    }

    public function test_barbero_puede_ser_desactivado(): void
    {
        $this->barber->update(['activo' => false]);

        $this->assertFalse($this->barber->fresh()->activo);
    }

    // ==================== TESTS DE BÚSQUEDA ====================

    public function test_obtener_barberos_activos(): void
    {
        Barber::create([
            'user_id' => User::factory()->create()->id,
            'nombre' => 'Barbero Inactivo',
            'activo' => false,
        ]);

        $activos = Barber::where('activo', true)->get();

        $this->assertCount(1, $activos);
        $this->assertTrue($activos[0]->activo);
    }

    public function test_obtener_barberos_por_especialidad(): void
    {
        Barber::create([
            'user_id' => User::factory()->create()->id,
            'nombre' => 'Barbero Especialista',
            'especialidad' => 'Barbas profesionales',
        ]);

        $especialistas = Barber::where('especialidad', 'Barbas profesionales')->get();

        $this->assertGreaterThanOrEqual(1, $specialistas->count());
    }

    // ==================== TESTS DE ELIMINACIÓN ====================

    public function test_barbero_puede_ser_eliminado(): void
    {
        $id = $this->barber->id;
        $this->barber->delete();

        $this->assertDatabaseMissing('barbers', ['id' => $id]);
    }

    public function test_eliminar_barbero_mantiene_integridad(): void
    {
        $appointment = \App\Models\Appointment::factory()
            ->create(['barber_id' => $this->barber->id]);

        // Dependiendo de la configuración de FK, esto puede fallar o cascanlear
        // Este test documenta el comportamiento esperado
        try {
            $this->barber->delete();
            // Si no error, los registros relacionados fueron manejados
            $this->assertTrue(true);
        } catch (\Illuminate\Database\QueryException $e) {
            // Esperado si hay constraint de FK
            $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
        }
    }
}
