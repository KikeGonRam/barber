<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Nombre de un cliente para mostrarlo en lugares públicos (reseñas, comentarios del muro):
 * «Luis Enrique González Ramírez» -> «Luis G.». Así nadie sin sesión puede sacar el nombre
 * completo de quien reseñó o comentó.
 */
final class PublicName
{
    public static function of(?string $fullName): ?string
    {
        $parts = preg_split('/\s+/u', trim((string) $fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return null;
        }

        $first = $parts[0];
        // Con 4+ palabras (nombres, apellidos) la inicial es la del primer apellido.
        $surname = count($parts) >= 4 ? $parts[2] : ($parts[1] ?? null);

        return $surname === null ? $first : $first.' '.Str::upper(Str::substr($surname, 0, 1)).'.';
    }
}
