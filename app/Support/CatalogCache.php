<?php

namespace App\Support;

use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caché de las respuestas públicas del catálogo (servicios, productos sin filtros y la parte pública de barberos).
 *
 * HT-20: en la prueba de carga de staging cada petición del catálogo iba a Atlas M0 (plan con tope de operaciones
 * por segundo) y la API se saturó hacia las 7 req/s. El catálogo cambia poco y lo pide cada visitante de la landing,
 * de /reservar y de la app: se guarda 60 s en el almacén de caché por defecto (archivo en staging) y se borra en
 * cuanto un modelo del catálogo se guarda o se elimina.
 *
 * Límite conocido: con varias tareas cada una tiene su propio archivo, y una actualización masiva sin eventos
 * (query()->update()) no invalida; en ambos casos el dato viejo dura como máximo TTL segundos.
 */
final class CatalogCache
{
    public const SERVICES = 'catalog:services';

    public const PRODUCTS = 'catalog:products';

    public const BARBERS = 'catalog:barbers';

    public const TTL = 60;

    /**
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public static function remember(string $key, Closure $build): mixed
    {
        return Cache::remember($key, self::TTL, $build);
    }

    public static function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /** Borra la caché cuando cambia algo que aparece en el catálogo (se llama desde AppServiceProvider). */
    public static function registerInvalidation(): void
    {
        foreach (['saved', 'deleted'] as $event) {
            Service::$event(fn () => self::forget(self::SERVICES));
            Product::$event(fn () => self::forget(self::PRODUCTS));
            Barber::$event(fn () => self::forget(self::BARBERS));
            BarberReview::$event(fn () => self::forget(self::BARBERS));
        }

        // El nombre y la foto del barbero vienen de su usuario.
        User::saved(function (User $user): void {
            if ($user->wasChanged(['name', 'avatar_url'])) {
                self::forget(self::BARBERS);
            }
        });
    }
}
