<?php

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Monitor::query()
        ->whereNull('last_checked_at')
        ->orWhereRaw('last_checked_at <= datetime("now", "-" || check_interval || " minutes")')
        ->cursor()
        ->each(fn (Monitor $monitor) => CheckMonitorJob::dispatch($monitor->id));
})->everyMinute();
