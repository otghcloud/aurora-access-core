<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:reconcile-reader-lock-state')->everyMinute()->withoutOverlapping();
Schedule::command('app:health-access-control')->everyFiveMinutes()->withoutOverlapping();
