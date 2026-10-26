<?php

namespace Tests\Unit;

use App\Support\UserExperience;
use PHPUnit\Framework\TestCase;

class UserExperienceTest extends TestCase
{
    public function test_staff_experience_wins_over_client_without_granting_any_permission(): void
    {
        foreach (UserExperience::PRIORITY as $role) {
            $this->assertSame($role, UserExperience::primaryRole(['cliente', $role]));
            $this->assertSame($role, UserExperience::primaryRole([$role, 'cliente']));
        }
        $this->assertSame('recepcionista', UserExperience::primaryRole(['barbero', 'recepcionista']));
        $this->assertSame('barbero', UserExperience::primaryRole(['ingeniero', 'barbero']));
        $this->assertSame('guest', UserExperience::primaryRole(['desconocido']));
        $this->assertSame('guest', UserExperience::primaryRole([]));
    }
}
