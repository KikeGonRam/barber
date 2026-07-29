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
}
