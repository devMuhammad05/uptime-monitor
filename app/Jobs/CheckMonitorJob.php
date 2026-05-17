<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\MonitorCheckerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckMonitorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $monitorId) {}

    public function handle(MonitorCheckerService $service): void
    {
        /** @var Monitor|null $monitor */
        $monitor = Monitor::find($this->monitorId);

        if ($monitor === null) {
            return;
        }

        $service->run($monitor);
    }
}
