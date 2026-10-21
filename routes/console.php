<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SRS Sections 49 & 193: Automatically expire 15-minute temporary unpaid holds every minute
Schedule::command('bookings:expire-holds')->everyMinute();

// SRS Sections 37 & 126: Aggregate daily real estate market analytics
Schedule::command('analytics:aggregate-daily')->dailyAt('00:05');
