<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductNormalizationTest extends TestCase
{
    public function test_product_payload_normalizes_legacy_api_fields(): void
    {
        $payload = Product::normalizePayload([
            'tipo' => Product::LEGACY_TYPE_SALE,
            'active' => false,
        ]);

        $this->assertSame(Product::TYPE_SALE, $payload['tipo']);
        $this->assertFalse($payload['activo']);
        $this->assertArrayNotHasKey('active', $payload);
    }

    public function test_supply_legacy_type_normalizes_to_current_type(): void
    {
        $this->assertSame(
            Product::TYPE_SUPPLY,
            Product::normalizedType(Product::LEGACY_TYPE_SUPPLY),
        );
    }

    /**
     * Regresión: la regla de validación 'boolean' de Laravel valida el
     * formato pero no transforma el valor — un checkbox de formulario llega
     * como string "1", no como bool true. Guardar eso tal cual en Mongo
     * rompía silenciosamente cualquier where('activo', true) posterior
     * (alerta de stock bajo, listados de productos activos).
     */
    public function test_normalize_payload_casts_string_activo_to_real_boolean(): void
    {
        $this->assertTrue(Product::normalizePayload(['activo' => '1'])['activo']);
        $this->assertFalse(Product::normalizePayload(['activo' => '0'])['activo']);
        $this->assertFalse(Product::normalizePayload(['activo' => 'false'])['activo']);
        $this->assertNull(Product::normalizePayload(['activo' => null])['activo']);
    }

    /**
     * Misma clase de bug que activo: la regla 'integer' no transforma el
     * string de un <input type="number"> a int antes de guardarlo en Mongo,
     * lo que rompía comparaciones $expr entre stock_actual y stock_minimo.
     */
    public function test_normalize_payload_casts_string_stock_fields_to_integers(): void
    {
        $payload = Product::normalizePayload(['stock_actual' => '3', 'stock_minimo' => '10']);

        $this->assertSame(3, $payload['stock_actual']);
        $this->assertSame(10, $payload['stock_minimo']);
    }
}
