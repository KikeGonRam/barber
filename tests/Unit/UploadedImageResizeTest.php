<?php

namespace Tests\Unit;

use App\Support\UploadedImage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/**
 * UploadedImage::resizeAndStore() (25-oct-2026, hallazgo de PageSpeed Insights): una foto subida
 * de 1051×1405 llegaba íntegra a cada visitante aunque nunca se muestra a más de unos cientos de
 * px. Estas pruebas cubren el redimensionado real (con GD, no un mock) y que nunca se agranda
 * una imagen más chica que el límite.
 */
class UploadedImageResizeTest extends TestCase
{
    public function test_una_imagen_mas_ancha_que_el_limite_se_reduce_sin_deformarse(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('foto-grande.jpg', 2000, 1000);

        $path = UploadedImage::resizeAndStore($file, 'pruebas');

        Storage::disk('public')->assertExists($path);

        $contenido = Storage::disk('public')->get($path);
        $dimensiones = ImageManager::gd()->read($contenido)->size();

        $this->assertSame(1600, $dimensiones->width());
        // 2000x1000 -> 1600x800: misma proporción 2:1, nunca se deforma.
        $this->assertSame(800, $dimensiones->height());
    }

    public function test_una_imagen_mas_chica_que_el_limite_no_se_agranda(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('foto-chica.png', 300, 200);

        $path = UploadedImage::resizeAndStore($file, 'pruebas');

        $contenido = Storage::disk('public')->get($path);
        $dimensiones = ImageManager::gd()->read($contenido)->size();

        $this->assertSame(300, $dimensiones->width());
        $this->assertSame(200, $dimensiones->height());
    }

    public function test_la_ruta_guardada_conserva_la_extension_original(): void
    {
        Storage::fake('public');

        $path = UploadedImage::resizeAndStore(UploadedFile::fake()->image('avatar.png', 500, 500), 'pruebas');

        $this->assertStringEndsWith('.png', $path);
        $this->assertStringStartsWith('pruebas/', $path);
    }

    public function test_store_borra_la_imagen_anterior_de_la_misma_carpeta(): void
    {
        $rutaAnterior = 'pruebas/vieja.jpg';

        Storage::fake('public');
        Storage::disk('public')->put($rutaAnterior, 'contenido-viejo');

        $request = Request::create('/', 'POST', [], [], [
            'foto' => UploadedFile::fake()->image('nueva.jpg', 500, 500),
        ]);

        $path = UploadedImage::store($request, 'foto', 'pruebas', $rutaAnterior);

        Storage::disk('public')->assertMissing($rutaAnterior);
        Storage::disk('public')->assertExists($path);
    }
}
