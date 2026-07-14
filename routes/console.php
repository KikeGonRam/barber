<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('appointments:send-reminders')->everyTenMinutes();
Schedule::command('tokens:clean-expired')->daily();
Schedule::command('loyalty:draw-raffle')->monthlyOn(1, '08:00');
Schedule::command('inventory:low-stock-alert')->dailyAt('09:00');
Schedule::command('reports:daily-summary')->dailyAt('21:30');
Schedule::command('campaigns:dispatch-due')->everyFiveMinutes();
Schedule::command('appointments:mark-no-show')->hourly();
