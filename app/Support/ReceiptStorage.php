<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Acceso al disco 'receipts' (comprobantes de transferencia y recibos PDF).
 * En S3 el bucket es privado: las URLs son firmadas y expiran. En local
 * (desarrollo/pruebas) devuelve la URL directa de siempre.
 */
class ReceiptStorage
{
    public const URL_TTL_MINUTES = 15;

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('receipts');

        if (config('filesystems.disks.receipts.driver') === 's3') {
            return $disk->temporaryUrl($path, now()->addMinutes(self::URL_TTL_MINUTES));
        }

        return $disk->url($path);
    }

    /**
     * Ruta local legible por herramientas externas (Tesseract). En S3 descarga
     * el objeto a un archivo temporal; el llamador debe borrarlo con
     * unlinkIfTemporary().
     */
    public static function localCopy(string $path): string
    {
        $disk = Storage::disk('receipts');

        if (config('filesystems.disks.receipts.driver') !== 's3') {
            return $disk->path($path);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'receipt_').'.'.pathinfo($path, PATHINFO_EXTENSION);
        file_put_contents($tmp, $disk->get($path));

        return $tmp;
    }

    public static function unlinkIfTemporary(string $localPath): void
    {
        if (config('filesystems.disks.receipts.driver') === 's3' && is_file($localPath)) {
            @unlink($localPath);
        }
    }
}
