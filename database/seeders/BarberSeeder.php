<?php

namespace Database\Seeders;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Siembra ÚNICAMENTE la colección `barbers` — el perfil profesional de cada
 * usuario con rol "barbero" que ya haya creado UserSeeder.
 *
 * Ejecutar DESPUÉS de UserSeeder.
 */
class BarberSeeder extends Seeder
{
    // Pool de especialidades para distribuir entre barberos
    private const ESPECIALIDADES = [
        'Fade, Barba',
        'Corte clásico, Barba',
        'Undercut, Diseño',
        'Pompadour, Fade',
        'Corte degradado, Afeitado',
        'Corte texturizado, Barba',
        'Skin Fade, Diseño capilar',
        'Buzz cut, Perfilado',
        'Quiff, Corte moderno',
        'Corte clásico, Fade, Barba',
    ];

    public function run(): void
    {
        // NOTA: User::role('barbero') usa un scope de consulta MorphToMany que
        // el driver mongodb/laravel-mongodb no soporta ("hybrid query
        // constraints" — ver unidad_6_caso_aplicado_laravel/02_causa_raiz_tecnica.md
        // en el proyecto spark). Se filtra en PHP con hasRole(), que sí funciona.
        //
        // Se recorre en chunks (no User::all()) para no cargar los ~1000+
        // usuarios completos en memoria de una sola vez — con el total de
        // usuarios reales esto llegó a agotar el memory_limit por defecto.
        $especialidades = self::ESPECIALIDADES;
        $espCount       = count($especialidades);
        $i              = 0;

        User::query()->chunk(100, function ($chunk) use (&$i, $especialidades, $espCount) {
            foreach ($chunk as $user) {
                if (! $user->hasRole('barbero')) {
                    continue;
                }
                $i++;

                $slugBase = Str::slug($user->name);
                $slug     = $slugBase;
                $si       = 2;
                while (Barber::where('slug', $slug)->where('user_id', '!=', $user->id)->exists()) {
                    $slug = "{$slugBase}-{$si}";
                    $si++;
                }

                Barber::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'slug'           => $slug,
                        'especialidades' => $especialidades[($i - 1) % $espCount],
                        'descripcion'    => "Barbero profesional con más de {$i} años de experiencia en UrbanBlade.",
                        'foto'           => null,
                        'activo'         => true,
                    ]
                );
            }
        });

        if ($i === 0) {
            $this->command->error('BarberSeeder: no hay usuarios con rol barbero. Ejecuta UserSeeder primero.');
            return;
        }

        $this->command->info("  ✓ {$i} perfiles de barbero creados");
    }
}
