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
        ->cursor()
        ->filter(fn (Monitor $monitor) => $monitor->last_checked_at === null ||
            $monitor->last_checked_at->addMinutes($monitor->check_interval)->lte(now())
        )
        ->each(fn (Monitor $monitor) => CheckMonitorJob::dispatch($monitor->id));
})->everyMinute();
