<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check deadlines every 5 minutes for timely notifications (1h/3h/12h windows)
Schedule::command('deadlines:notify')->everyFiveMinutes();
