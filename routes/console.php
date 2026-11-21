<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('finance:about', function () {
    $this->info('Student Finance Manager — Laravel 13 / PostgreSQL migration branch.');
})->purpose('Display migration branch information');

Schedule::command('finance:process-recurring')\n    ->dailyAt('00:05')\n    ->timezone(config('app.timezone'))\n    ->withoutOverlapping();\n\nSchedule::command('finance:generate-reminders')
    ->dailyAt('08:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
