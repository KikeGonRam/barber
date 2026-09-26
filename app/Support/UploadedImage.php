<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imagen de catálogo (servicios, barberos) que puede llegar como archivo elegido en el
 * dispositivo o, por compatibilidad con clientes anteriores, como texto (ruta o URL).
 *
 * Con archivo el formulario debe ir como POST multipart con `_method=PUT` al editar:
 * PHP no lee archivos de un PUT multipart.
 */
class UploadedImage
{
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

        $path = $request->file($field)->store($folder, 'public');

        if ($previous && Str::startsWith($previous, $folder.'/') && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }

        return $path ?: null;
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
