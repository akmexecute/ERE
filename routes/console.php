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

// SRS Sections 18 & 124: Process saved search alert matches
Schedule::command('alerts:process-saved-searches')->hourly();

// Phase 2 Milestone 2: Process stay lifecycle triggers, missed checkouts, 48h deposit releases, and 14d review publishing
Schedule::command('stay:process-lifecycle')->hourly();
