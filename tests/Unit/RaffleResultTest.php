<?php

namespace Tests\Unit;

use App\Models\RaffleResult;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RaffleResultTest extends TestCase
{
    private function makeResult(?Carbon $vence, ?Carbon $reclamado): RaffleResult
    {
        $result = new RaffleResult;
        $result->vence_en = $vence;
        $result->reclamado_en = $reclamado;

        return $result;
    }

    public function test_is_claimed_when_reclamado_en_is_set(): void
    {
        $result = $this->makeResult(Carbon::now()->addDays(10), Carbon::now());

        $this->assertTrue($result->isClaimed());
        $this->assertFalse($result->isRedeemable());
    }

    public function test_is_not_claimed_when_reclamado_en_is_null(): void
    {
        $result = $this->makeResult(Carbon::now()->addDays(10), null);

        $this->assertFalse($result->isClaimed());
    }

    public function test_is_expired_when_past_vence_en_and_unclaimed(): void
    {
        $result = $this->makeResult(Carbon::now()->subDay(), null);

        $this->assertTrue($result->isExpired());
        $this->assertFalse($result->isRedeemable());
    }

    public function test_is_not_expired_when_claimed_even_past_vence_en(): void
    {
        // Una vez reclamado, la fecha de vencimiento ya no importa: no debe
        // marcarse como "caducado" un premio que ya se usó.
        $result = $this->makeResult(Carbon::now()->subDay(), Carbon::now()->subDays(2));

        $this->assertFalse($result->isExpired());
        $this->assertTrue($result->isClaimed());
        $this->assertFalse($result->isRedeemable());
    }

    public function test_is_redeemable_when_not_claimed_and_not_expired(): void
    {
        $result = $this->makeResult(Carbon::now()->addDays(5), null);

        $this->assertTrue($result->isRedeemable());
    }
}
