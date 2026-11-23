<?php

namespace App\Services\Mail;

use App\Models\BarbershopSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Datos de la barbería para correos y facturas. Antes el pie de los correos y de las facturas llevaba datos de
 * ejemplo («Av. Reforma 123, CDMX», «+52 55 1234 5678», «hola@urbanblade.com»); ahora salen de la configuración
 * de la barbería y, si un dato no está capturado, simplemente no se muestra.
 */
final class ShopBranding
{
    /** Identificador del logo incrustado en los correos (ver EmbedMailLogo). */
    public const LOGO_CID = 'urbanblade-logo@urbanblade.mx';

    /**
     * @return array{nombre: string, direccion: string|null, telefono: string|null, email: string|null}
     */
    public static function contact(): array
    {
        $setting = null;
        try {
            $setting = BarbershopSetting::cached();
        } catch (\Throwable) {
            // Sin base de datos (p. ej. al previsualizar) se usan los valores por defecto.
        }

        $clean = static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null;
        $from = $clean(config('mail.from.address'));

        return [
            'nombre' => $clean($setting?->getAttribute('nombre')) ?? (string) config('app.name', 'UrbanBlade'),
            'direccion' => $clean($setting?->getAttribute('direccion')),
            'telefono' => $clean($setting?->getAttribute('telefono')),
            'email' => $from !== null && ! preg_match('/(@example\.com|\.test|\.local)$/i', $from) ? $from : null,
        ];
    }

    /**
     * Fecha legible en español para los correos: «viernes 28 de noviembre» (o «28 de noviembre de 2026» con año).
     * Antes salía «28/11/2026», que obliga a calcular el día de la semana. Null si no hay fecha.
     */
    public static function fecha(mixed $date, bool $conAnio = false): ?string
    {
        // Los atributos de fecha de los modelos pueden llegar como Carbon, como texto o vacíos.
        if (is_string($date) && trim($date) !== '') {
            $date = Carbon::parse($date);
        }

        if (! $date instanceof CarbonInterface) {
            return null;
        }

        return $date->copy()->locale('es')->translatedFormat($conAnio ? 'j \d\e F \d\e Y' : 'l j \d\e F');
    }

    /** URL del sitio web (Nuxt), sin barra final. */
    public static function frontendUrl(string $path = ''): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }

    /** Rutas web que nunca pasan por /abrir: llevan tokens o datos de sesión y deben ir directo al navegador. */
    private const NEVER_SMART = ['/reset-password', '/forgot-password', '/login', '/register', '/verify'];

    /**
     * Enlace inteligente para los botones de los correos: `{sitio}/abrir?ruta=/my/invoices`. La web decide según el
     * dispositivo (celular Android → app UrbanBlade; computadora u otro → navegador). Solo envuelve enlaces del
     * propio sitio (y `/dashboard` de la API, que redirige al sitio); cualquier otro enlace queda igual.
     */
    public static function smartLink(string $url): string
    {
        $frontend = self::frontendUrl();
        $api = rtrim((string) config('app.url'), '/');

        if ($frontend !== '' && str_starts_with($url, $frontend)) {
            $path = substr($url, strlen($frontend));
        } elseif ($api !== '' && ($url === $api.'/dashboard' || str_starts_with($url, $api.'/dashboard?'))) {
            $path = substr($url, strlen($api));
        } else {
            return $url;
        }

        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return $url;
        }

        $pathname = strtok($path, '?#') ?: '/';
        foreach (self::NEVER_SMART as $prefix) {
            if ($pathname === $prefix || str_starts_with($pathname, $prefix.'/')) {
                return $url;
            }
        }

        return $frontend.'/abrir?ruta='.rawurlencode($path);
    }

    /** El logo como data URI, para las facturas en PDF (DomPDF no necesita salir a internet por la imagen). */
    public static function logoDataUri(): string
    {
        $file = public_path('images/urbanblade-mail-logo.png');

        return is_file($file) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($file)) : '';
    }
}
