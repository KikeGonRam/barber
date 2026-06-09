<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Work;
use Tests\Support\RefreshMongoDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkUploadTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_barber_can_upload_work_with_multiple_images()
    {
        Storage::fake('public');
        $barber = User::factory()->create();
        $barber->assignRole('barbero');

        $this->actingAs($barber);

        $response = $this->post(route('barbers.works.store', $barber), [
            'title' => 'Corte Fade',
            'description' => 'Estilo moderno',
            'images' => [
                UploadedFile::fake()->image('foto1.png'),
                UploadedFile::fake()->image('foto2.png'),
            ],
        ]);

        $response->assertRedirect(route('barbers.show', $barber));
        $this->assertDatabaseHas('works', [
            'barbero_id' => $barber->id,
            'title' => 'Corte Fade',
        ]);

        $work = Work::where('barbero_id', $barber->id)->first();
        $this->assertCount(2, $work->images);
        foreach ($work->images as $img) {
            Storage::disk('public')->assertExists($img->image);
        }
    }

    public function test_validation_fails_without_images()
    {
        $barber = User::factory()->create();
        $barber->assignRole('barbero');
        $this->actingAs($barber);

        $response = $this->post(route('barbers.works.store', $barber), [
            'title' => 'Corte Fade',
            'description' => 'Estilo moderno',
            // No images
        ]);

        $response->assertSessionHasErrors(['images']);
    }
}
