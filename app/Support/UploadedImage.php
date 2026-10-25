<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\FileExtensionEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

/**
 * Imagen de catálogo (servicios, barberos) que puede llegar como archivo elegido en el
 * dispositivo o, por compatibilidad con clientes anteriores, como texto (ruta o URL).
 *
 * Con archivo el formulario debe ir como POST multipart con `_method=PUT` al editar:
 * PHP no lee archivos de un PUT multipart.
 */
class UploadedImage
{
    /**
     * Ningún lugar donde se muestra una foto de catálogo/perfil la pinta a más de esto de ancho
     * (ver srcset en frontend-urban, publicImageUrl). Bajarla aquí es lo que de verdad pesa: el
     * hallazgo real de PageSpeed Insights (25-oct-2026) fue una foto de 1051×1405 mostrada a
     * 254×362 — 2.1 MB que bajaban a cada visitante por una imagen subida sin redimensionar.
     */
    private const MAX_WIDTH = 1600;

    /**
     * GD decodifica el bitmap completo en memoria ANTES de que scaleDown() pueda reducirlo
     * (no hay decode progresivo) -- un archivo de pocos MB con dimensiones absurdas ("bomba de
     * descompresión") puede tronar el límite de memoria de PHP (512 MiB) y matar el worker.
     * 40 MP es más que cualquier cámara de celular guarda por defecto, pero a 4 bytes/px con el
     * overhead típico de GD se queda cómodo bajo ese límite.
     */
    private const MAX_DECODE_PIXELS = 40_000_000;

    /** @return array<int, string> */
    public static function rules(Request $request, string $field): array
    {
        return $request->hasFile($field)
            ? ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048']
            : ['nullable', 'string', 'max:255'];
    }

    /**
     * Si llegó un archivo lo guarda en el disco público y borra el anterior (solo si también
     * era un archivo nuestro de esa carpeta). Devuelve la ruta guardada o null si no hubo archivo.
     */
    public static function store(Request $request, string $field, string $folder, ?string $previous = null): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $path = self::resizeAndStore($request->file($field), $folder);

        if ($previous && Str::startsWith($previous, $folder.'/') && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }

        return $path ?: null;
    }

    /**
     * Redimensiona (sin agrandar si ya es más chica) y guarda con caché larga: el nombre es
     * aleatorio (Str::uuid), así que esa URL nunca cambia de contenido y puede cachearse para
     * siempre -- "immutable" es justo lo que PageSpeed pedía en "usar tiempos de vida de caché
     * eficientes" (25-oct-2026, 557 KiB de ahorro estimado solo por esto).
     *
     * Si Intervention/GD fallara con un archivo que Laravel ya validó como imagen (p. ej. un
     * formato exótico que `image/*` acepta pero GD no decodifica, o dimensiones que superan
     * MAX_DECODE_PIXELS), se guarda el original tal cual en vez de perder la subida -- mejor
     * una imagen sin optimizar que un 500 al cliente. Pero si ni siquiera ese respaldo logra
     * escribirse (disco/S3 realmente caído), sí lanza: nunca devuelve una ruta que no exista.
     *
     * @throws RuntimeException si no se pudo escribir ni la versión optimizada ni el respaldo.
     */
    public static function resizeAndStore(UploadedFile $file, string $folder, string $disk = 'public'): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));
        $path = $folder.'/'.Str::uuid().'.'.$extension;
        $cacheControl = ['CacheControl' => 'public, max-age=31536000, immutable'];

        try {
            $dimensions = @getimagesize($file->getRealPath());

            if ($dimensions && ($dimensions[0] * $dimensions[1]) > self::MAX_DECODE_PIXELS) {
                throw new RuntimeException(sprintf(
                    'Imagen de %dx%d px supera el límite de decodificación (%d px).',
                    $dimensions[0],
                    $dimensions[1],
                    self::MAX_DECODE_PIXELS
                ));
            }

            $encoded = ImageManager::gd()
                ->read($file->getRealPath())
                ->scaleDown(width: self::MAX_WIDTH)
                ->encode(new FileExtensionEncoder($extension));

            if (! Storage::disk($disk)->put($path, $encoded->toString(), $cacheControl)) {
                throw new RuntimeException("No se pudo escribir la imagen redimensionada en el disco '{$disk}'.");
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo redimensionar la imagen subida; se guarda sin optimizar.', [
                'extension' => $extension,
                'error' => $exception->getMessage(),
            ]);

            // Si esto también falla (p. ej. el disco/S3 está realmente caído), SÍ se propaga: mejor
            // un 500 que devolver una ruta "exitosa" que apunta a un archivo que nunca se escribió
            // -- store() borraría la imagen anterior y dejaría al usuario sin ninguna de las dos.
            if (! Storage::disk($disk)->putFileAs($folder, $file, basename($path), $cacheControl)) {
                throw new RuntimeException("No se pudo guardar la imagen subida en el disco '{$disk}'.");
            }
        }

        return $path;
    }

    /** URL pública, o null si no hay imagen. */
    public static function url(?string $path): ?string
    {
        return $path ? self::urlFor($path) : null;
    }

    /**
     * URL pública de una ruta que sí existe. Las URL completas guardadas como texto (p. ej. los
     * trabajos de ejemplo con fotos de Unsplash) se devuelven tal cual: antes se les anteponía el
     * bucket y quedaban rotas ("…s3…/https://images.unsplash.com/…").
     */
    public static function urlFor(string $path): string
    {
        return Str::startsWith($path, ['http://', 'https://']) ? $path : Storage::disk('public')->url($path);
    }
}
