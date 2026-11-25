<?php

namespace Tests\UT;

use Tests\TestCase;

/**
 * config/features.php (auditoría de funciones, 23-nov-2026): interruptores de módulos que el dueño del
 * proyecto apagó. Evalúa el archivo de configuración directamente con variables de entorno distintas, porque
 * la configuración ya cargada por la aplicación de pruebas corresponde a phpunit.xml (todas en "true", para
 * que la suite existente de cada módulo siga corriendo sin tocarla). Mismo patrón que
 * tests/OAT/PublicUploadsCacheControlTest.php.
 */
class FeatureFlagsTest extends TestCase
{
    private const KEYS = [
        'FEATURE_PACKAGES',
        'FEATURE_GIFT_CARDS',
        'FEATURE_REFERRALS',
        'FEATURE_RAFFLES',
        'FEATURE_SOCIAL',
        'FEATURE_CAMPAIGNS',
    ];

    /**
     * @param  array<string, string>  $env
     * @return array<string, bool>
     */
    private function featuresConfig(array $env): array
    {
        // PHPUnit deja los valores de phpunit.xml en $_ENV/$_SERVER aunque getenv() no los vea: cada fuente se
        // guarda y se restaura por separado, o la siguiente prueba arrancaría con los interruptores en false.
        $previous = [];

        foreach (self::KEYS as $key) {
            $previous[$key] = [
                'getenv' => getenv($key),
                'env' => array_key_exists($key, $_ENV) ? $_ENV[$key] : null,
                'server' => array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null,
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
            return $this->app['files']->getRequire(config_path('features.php'));
        } finally {
            foreach (self::KEYS as $key) {
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

    public function test_sin_ninguna_variable_de_entorno_todos_los_modulos_quedan_apagados(): void
    {
        $this->assertSame(
            ['packages' => false, 'gift_cards' => false, 'referrals' => false, 'raffles' => false, 'social' => false, 'campaigns' => false],
            $this->featuresConfig([]),
        );
    }

    public function test_cada_variable_enciende_solo_su_propio_modulo(): void
    {
        $config = $this->featuresConfig(['FEATURE_RAFFLES' => 'true']);

        $this->assertTrue($config['raffles']);
        $this->assertFalse($config['packages']);
        $this->assertFalse($config['gift_cards']);
        $this->assertFalse($config['referrals']);
        $this->assertFalse($config['social']);
        $this->assertFalse($config['campaigns']);
    }

    public function test_el_entorno_de_pruebas_los_tiene_todos_encendidos(): void
    {
        // phpunit.xml fija FEATURE_*=true a propósito, para que la suite existente de cada módulo (paquetes,
        // gift cards, referidos, sorteos, muro, campañas) siga corriendo sin que cada prueba lo encienda.
        $this->assertSame(
            ['packages' => true, 'gift_cards' => true, 'referrals' => true, 'raffles' => true, 'social' => true, 'campaigns' => true],
            config('features'),
        );
    }
}
