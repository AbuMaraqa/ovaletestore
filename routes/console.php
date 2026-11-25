<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Audit trail retention: config('activitylog.clean_after_days').
Schedule::command('activitylog:clean --force')->dailyAt('03:30');

// Nightly backup (spatie/laravel-backup, disks in config/backup.php — add an offsite disk in production).
Schedule::command('backup:clean')->dailyAt('01:30');
Schedule::command('backup:run')->dailyAt('02:00');
Schedule::command('queue:prune-failed --hours=720')->weekly();
