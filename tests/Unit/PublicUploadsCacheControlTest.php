<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Las imágenes subidas al bucket público (productos, servicios, avatares, portafolio)
 * deben salir de S3 con Cache-Control: sin él cada visita vuelve a pedirlas o a
 * revalidarlas. Los comprobantes (disco "receipts", bucket privado) no deben llevarlo.
 */
class PublicUploadsCacheControlTest extends TestCase
{
    /**
     * Evalúa config/filesystems.php con las variables dadas, porque la configuración
     * ya cargada por la aplicación de pruebas corresponde al .env.testing (disco local).
     *
     * @param  array<string, string>  $env
     * @return array<string, mixed>
     */
    private function filesystemsConfig(array $env): array
    {
        $keys = ['UPLOADS_BUCKET', 'RECEIPTS_BUCKET'];
        $previous = [];

        foreach ($keys as $key) {
            $previous[$key] = [
                'getenv' => getenv($key),
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
            ];
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            return $this->app['files']->getRequire(config_path('filesystems.php'));
        } finally {
            foreach ($keys as $key) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);

                if ($previous[$key]['getenv'] !== false) {
                    putenv($key.'='.$previous[$key]['getenv']);
                }
                if ($previous[$key]['env'] !== null) {
                    $_ENV[$key] = $previous[$key]['env'];
                }
                if ($previous[$key]['server'] !== null) {
                    $_SERVER[$key] = $previous[$key]['server'];
                }
            }
        }
    }

    public function test_the_s3_public_disk_writes_uploads_with_a_long_immutable_cache_control(): void
    {
        $config = $this->filesystemsConfig(['UPLOADS_BUCKET' => 'ub-test-uploads']);

        $disk = $config['disks']['public'];

        $this->assertSame('s3', $disk['driver']);
        $this->assertSame('ub-test-uploads', $disk['bucket']);
        $this->assertSame(
            'public, max-age=31536000, immutable',
            $disk['options']['CacheControl'] ?? null,
        );
    }

    public function test_the_local_public_disk_has_no_s3_options(): void
    {
        $config = $this->filesystemsConfig([]);

        $disk = $config['disks']['public'];

        $this->assertSame('local', $disk['driver']);
        $this->assertArrayNotHasKey('options', $disk);
    }

    public function test_the_private_receipts_disk_never_gets_a_public_cache_control(): void
    {
        $config = $this->filesystemsConfig([
            'UPLOADS_BUCKET' => 'ub-test-uploads',
            'RECEIPTS_BUCKET' => 'ub-test-receipts',
        ]);

        $disk = $config['disks']['receipts'];

        $this->assertSame('s3', $disk['driver']);
        $this->assertSame('ub-test-receipts', $disk['bucket']);
        $this->assertArrayNotHasKey('options', $disk);
    }
}
