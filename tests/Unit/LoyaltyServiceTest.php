<?php

namespace Tests\Unit;

use App\Services\Loyalty\LoyaltyService;
use PHPUnit\Framework\TestCase;

class LoyaltyServiceTest extends TestCase
{
    public function test_nivel_from_citas_boundaries(): void
    {
        $this->assertSame('nuevo', LoyaltyService::nivelFromCitas(0));
        $this->assertSame('nuevo', LoyaltyService::nivelFromCitas(4));
        $this->assertSame('regular', LoyaltyService::nivelFromCitas(5));
        $this->assertSame('regular', LoyaltyService::nivelFromCitas(9));
        $this->assertSame('vip', LoyaltyService::nivelFromCitas(10));
        $this->assertSame('vip', LoyaltyService::nivelFromCitas(19));
        $this->assertSame('leyenda', LoyaltyService::nivelFromCitas(20));
        $this->assertSame('leyenda', LoyaltyService::nivelFromCitas(1000));
    }

    public function test_next_level_progression(): void
    {
        $this->assertSame('regular', LoyaltyService::nextLevel('nuevo'));
        $this->assertSame('vip', LoyaltyService::nextLevel('regular'));
        $this->assertSame('leyenda', LoyaltyService::nextLevel('vip'));
        $this->assertNull(LoyaltyService::nextLevel('leyenda'));
        $this->assertNull(LoyaltyService::nextLevel('nivel_desconocido'));
    }

    public function test_citas_for_level(): void
    {
        $this->assertSame(0, LoyaltyService::citasForLevel('nuevo'));
        $this->assertSame(5, LoyaltyService::citasForLevel('regular'));
        $this->assertSame(10, LoyaltyService::citasForLevel('vip'));
        $this->assertSame(20, LoyaltyService::citasForLevel('leyenda'));
        $this->assertSame(0, LoyaltyService::citasForLevel('nivel_invalido'));
    }

    public function test_discount_pct(): void
    {
        $this->assertSame(0, LoyaltyService::discountPct('nuevo'));
        $this->assertSame(5, LoyaltyService::discountPct('regular'));
        $this->assertSame(10, LoyaltyService::discountPct('vip'));
        $this->assertSame(15, LoyaltyService::discountPct('leyenda'));
        $this->assertSame(0, LoyaltyService::discountPct('nivel_invalido'));
    }

    public function test_apply_discount_rounds_to_two_decimals(): void
    {
        $this->assertSame(100.0, LoyaltyService::applyDiscount(100.0, 'nuevo'));
        $this->assertSame(95.0, LoyaltyService::applyDiscount(100.0, 'regular'));
        $this->assertSame(85.0, LoyaltyService::applyDiscount(100.0, 'leyenda'));
        $this->assertSame(33.25, LoyaltyService::applyDiscount(35.0, 'regular'));
    }

    public function test_max_redeemable_points_is_capped_at_half_the_total(): void
    {
        // Total 200, mitad = 100, pero el cliente solo tiene 40 -> el saldo manda.
        $this->assertSame(40, LoyaltyService::maxRedeemablePoints(200.0, 40));

        // Total 200, mitad = 100, cliente tiene 500 -> el 50% del total manda.
        $this->assertSame(100, LoyaltyService::maxRedeemablePoints(200.0, 500));

        // Total impar: floor(201 * 0.5) = 100.
        $this->assertSame(100, LoyaltyService::maxRedeemablePoints(201.0, 999));
    }

    public function test_max_redeemable_points_is_never_negative(): void
    {
        $this->assertSame(0, LoyaltyService::maxRedeemablePoints(0.0, 50));
        $this->assertSame(0, LoyaltyService::maxRedeemablePoints(100.0, 0));
    }
}
