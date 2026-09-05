<?php

namespace Tests\Unit;

use App\Console\Commands\SendBirthdayGreetingsCommand;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class SendBirthdayGreetingsCommandTest extends TestCase
{
    public function test_matches_a_birthday_on_the_exact_month_and_day_regardless_of_year(): void
    {
        $fechaNacimiento = Carbon::create(1995, 6, 15);
        $today = Carbon::create(2026, 6, 15);

        $this->assertTrue(SendBirthdayGreetingsCommand::isBirthdayToday($fechaNacimiento, $today));
    }

    public function test_does_not_match_a_different_day(): void
    {
        $fechaNacimiento = Carbon::create(1995, 6, 15);
        $today = Carbon::create(2026, 6, 16);

        $this->assertFalse(SendBirthdayGreetingsCommand::isBirthdayToday($fechaNacimiento, $today));
    }

    public function test_returns_false_when_there_is_no_birthdate(): void
    {
        $this->assertFalse(SendBirthdayGreetingsCommand::isBirthdayToday(null, Carbon::today()));
    }

    public function test_february_29th_birthday_matches_february_28th_in_a_non_leap_year(): void
    {
        $fechaNacimiento = Carbon::create(2000, 2, 29);
        $today = Carbon::create(2026, 2, 28); // 2026 no es bisiesto

        $this->assertTrue(SendBirthdayGreetingsCommand::isBirthdayToday($fechaNacimiento, $today));
    }

    public function test_february_29th_birthday_matches_itself_in_a_leap_year(): void
    {
        $fechaNacimiento = Carbon::create(2000, 2, 29);
        $today = Carbon::create(2028, 2, 29); // 2028 si es bisiesto

        $this->assertTrue(SendBirthdayGreetingsCommand::isBirthdayToday($fechaNacimiento, $today));
    }

    public function test_february_28th_birthday_does_not_match_february_29th_in_a_leap_year(): void
    {
        // No confundir: un cumpleanos real el 28 no debe "moverse" al 29.
        $fechaNacimiento = Carbon::create(2000, 2, 28);
        $today = Carbon::create(2028, 2, 29);

        $this->assertFalse(SendBirthdayGreetingsCommand::isBirthdayToday($fechaNacimiento, $today));
    }
}
