<?php

namespace App\Support;

/** Selecciona la experiencia visual; no concede permisos ni sustituye policies. */
final class UserExperience
{
    public const PRIORITY = ['administrador', 'recepcionista', 'barbero', 'ingeniero', 'cliente'];

    public static function primaryRole(iterable $roles): string
    {
        $assigned = is_array($roles) ? $roles : iterator_to_array($roles);

        foreach (self::PRIORITY as $role) {
            if (in_array($role, $assigned, true)) {
                return $role;
            }
        }

        return 'guest';
    }
}
