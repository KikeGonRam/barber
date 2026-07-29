<?php

namespace App\Services\Member;

use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Datos de la tarjeta de membresia (numero de socio, alta y QR), compartidos
 * por el dashboard del cliente y la tarjeta descargable en PDF.
 */
class MemberCardService
{
    public function memberNumber(User $user): string
    {
        $id = preg_replace('/[^0-9a-zA-Z]/', '', (string) $user->id);
        $id = strtoupper(str_pad(substr($id, -8), 8, '0', STR_PAD_LEFT));

        return 'UB · '.substr($id, 0, 4).' '.substr($id, 4, 4);
    }

    public function memberSince(User $user): string
    {
        return optional($user->created_at)->format('Y') ?? date('Y');
    }

    /**
     * QR con la identidad del socio (para escanear en recepcion). Devuelve un
     * data URI SVG, o null si la generacion falla.
     */
    public function qrDataUri(User $user): ?string
    {
        try {
            $payload = 'UB|'.$user->id.'|'.$user->name;

            $builder = new Builder(
                writer: new SvgWriter,
                data: $payload,
                size: 240,
                margin: 1,
            );

            return $builder->build()->getDataUri();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Version PNG del QR (mas confiable dentro de DomPDF que el SVG).
     */
    public function qrPngDataUri(User $user): ?string
    {
        try {
            $builder = new Builder(
                writer: new PngWriter,
                data: 'UB|'.$user->id.'|'.$user->name,
                size: 240,
                margin: 1,
            );

            return $builder->build()->getDataUri();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
