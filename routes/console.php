<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('analytics:flush')->everyMinute();
Schedule::command('analytics:partitions:ensure')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('analytics:partitions:archive --execute')->dailyAt('00:30')->withoutOverlapping();
