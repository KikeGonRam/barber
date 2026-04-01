<?php

namespace Tests\Feature\Storage;

/**
 * Cubre pruebas de archivos y storage para perfiles, portafolio y
 * comprobantes PDF de pago.
 */

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'verified',
        ]);

        Storage::fake('public');

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
        ]);
    }

    // --- Contexto: foto perfil barbero ---

    public function test_barber_can_upload_profile_photo_and_file_exists_in_expected_path(): void
    {
        $barberUser = $this->createVerifiedUserWithRole('barbero');

        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
        ]);

        $this->actingAs($barberUser)
            ->put(route('barber.profile.update'), [
                'especialidades' => 'fade',
                'descripcion' => 'Perfil con foto',
                'foto' => UploadedFile::fake()->image('perfil.jpg'),
            ])
            ->assertRedirect();

        $barber->refresh();

        $this->assertNotNull($barber->foto);
        $this->assertStringContainsString('barbers/' . $barberUser->id . '/', $barber->foto);
        Storage::disk('public')->assertExists($barber->foto);
    }

    // --- Contexto: trabajos de portafolio ---

    public function test_barber_uploads_work_with_three_images_creates_three_work_images_records(): void
    {
        $barberUser = $this->createVerifiedUserWithRole('barbero');

        $this->actingAs($barberUser)
            ->post(route('barbers.works.store', $barberUser), [
                'title' => 'Trabajo triple',
                'description' => 'Con tres fotos',
                'images' => [
                    UploadedFile::fake()->image('a.jpg'),
                    UploadedFile::fake()->image('b.jpg'),
                    UploadedFile::fake()->image('c.jpg'),
                ],
            ])
            ->assertRedirect(route('barbers.show', $barberUser));

        $work = \App\Models\Work::query()->where('barbero_id', $barberUser->id)->firstOrFail();

        $this->assertCount(3, $work->images);
        foreach ($work->images as $image) {
            Storage::disk('public')->assertExists($image->image);
        }
    }

    public function test_upload_without_images_returns_validation_error(): void
    {
        $barberUser = $this->createVerifiedUserWithRole('barbero');

        $this->actingAs($barberUser)
            ->post(route('barbers.works.store', $barberUser), [
                'title' => 'Sin imagenes',
                'description' => 'Debe fallar',
            ])
            ->assertSessionHasErrors('images');

        $this->assertDatabaseCount('works', 0);
    }

    public function test_upload_with_too_large_image_returns_validation_error(): void
    {
        $barberUser = $this->createVerifiedUserWithRole('barbero');

        $this->actingAs($barberUser)
            ->post(route('barbers.works.store', $barberUser), [
                'title' => 'Imagen grande',
                'images' => [
                    UploadedFile::fake()->image('grande.jpg')->size(6000),
                ],
            ])
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('works', 0);
    }

    public function test_upload_with_invalid_file_type_returns_validation_error(): void
    {
        $barberUser = $this->createVerifiedUserWithRole('barbero');

        $this->actingAs($barberUser)
            ->post(route('barbers.works.store', $barberUser), [
                'title' => 'Archivo invalido',
                'images' => [
                    UploadedFile::fake()->create('archivo.pdf', 200, 'application/pdf'),
                ],
            ])
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('works', 0);
    }

    // --- Contexto: comprobante PDF de pago ---

    public function test_payment_generates_pdf_file_and_path_is_saved_in_database(): void
    {
        $recepcionista = $this->createVerifiedUserWithRole('recepcionista');

        $barber = Barber::factory()->create(['activo' => true]);
        $client = Client::factory()->create();
        $service = Service::factory()->create(['activo' => true]);

        $appointment = Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '11:00',
            'hora_fin' => '11:30',
            'estado' => 'confirmada',
        ]);

        $this->actingAs($recepcionista)
            ->post(route('payments.store'), [
                'appointment_id' => $appointment->id,
                'monto' => 350,
                'metodo_pago' => 'efectivo',
                'propina' => 10,
            ])
            ->assertRedirect(route('payments.index'));

        $payment = Payment::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->assertNotNull($payment->comprobante_pdf);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'comprobante_pdf' => $payment->comprobante_pdf,
        ]);
        Storage::disk('public')->assertExists($payment->comprobante_pdf);
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
