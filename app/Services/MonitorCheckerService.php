<?php

namespace App\Services;

use App\Actions\CalculateUptimeAction;
use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Notifications\SiteDownNotification;
use App\Notifications\SiteUpNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class MonitorCheckerService
{
    private const TIMEOUT = 10;

    private const CONNECT_TIMEOUT = 5;

    public function __construct(private readonly CalculateUptimeAction $uptimeAction) {}

    public function run(Monitor $monitor): void
    {
        [$statusCode, $responseTimeMs, $isUp] = $this->performHttpCheck($monitor->url);

        MonitorCheck::create([
            'monitor_id' => $monitor->id,
            'status_code' => $statusCode,
            'response_time_ms' => $responseTimeMs,
            'is_up' => $isUp,
            'checked_at' => now(),
        ]);

        $previousStatus = $monitor->status;

        $this->applyThresholdLogic($monitor, $isUp);

        $monitor->update([
            'uptime_percentage' => $this->uptimeAction->execute($monitor),
            'last_checked_at' => now(),
        ]);

        $this->sendNotificationIfStatusChanged($monitor, $previousStatus);
    }

    /** @return array{int, int|null, bool} */
    private function performHttpCheck(string $url): array
    {
        try {
            $start = hrtime(true);

            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->withoutRedirecting()
                ->get($url);

            $responseTimeMs = (int) round((hrtime(true) - $start) / 1_000_000);
            $statusCode = $response->status();
            $isUp = $statusCode >= 200 && $statusCode < 400;

            return [$statusCode, $responseTimeMs, $isUp];
        } catch (Throwable $e) {
            Log::warning("Monitor check failed for {$url}: {$e->getMessage()}");

            return [0, null, false];
        }
    }

    private function applyThresholdLogic(Monitor $monitor, bool $isUp): void
    {
        if ($isUp) {
            $monitor->update([
                'consecutive_failures' => 0,
                'status' => MonitorStatus::Up,
            ]);

            return;
        }

        $monitor->increment('consecutive_failures');

        if ($monitor->consecutive_failures >= $monitor->threshold) {
            $monitor->update(['status' => MonitorStatus::Down]);
        }
    }

    private function sendNotificationIfStatusChanged(Monitor $monitor, MonitorStatus $previousStatus): void
    {
        $alertEmail = config('services.monitor.alert_email');

        if (! $alertEmail) {
            return;
        }

        $currentStatus = $monitor->status;

        if ($previousStatus !== MonitorStatus::Down && $currentStatus === MonitorStatus::Down) {
            Notification::route('mail', $alertEmail)->notify(new SiteDownNotification($monitor));
        } elseif ($previousStatus === MonitorStatus::Down && $currentStatus === MonitorStatus::Up) {
            Notification::route('mail', $alertEmail)->notify(new SiteUpNotification($monitor));
        }
    }
}
