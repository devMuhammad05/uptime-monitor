<?php

use App\Enums\MonitorStatus;
use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Notifications\SiteDownNotification;
use App\Notifications\SiteUpNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

describe('CheckMonitorJob', function (): void {
    beforeEach(function (): void {
        Http::preventStrayRequests();
        Notification::fake();
    });

    describe('HTTP check and MonitorCheck recording', function (): void {
        it('records a 200 response as is_up with a non-null response_time_ms', function (): void {
            Http::fake(['*' => Http::response('OK', 200)]);
            $monitor = Monitor::factory()->create();

            CheckMonitorJob::dispatchSync($monitor->id);

            $check = MonitorCheck::query()->first();
            expect($check->status_code)->toBe(200)
                ->and($check->is_up)->toBeTrue()
                ->and($check->response_time_ms)->not->toBeNull();
        });

        it('records a 301 redirect as is_up=true', function (): void {
            Http::fake(['*' => Http::response('', 301)]);
            $monitor = Monitor::factory()->create();

            CheckMonitorJob::dispatchSync($monitor->id);

            $check = MonitorCheck::query()->first();
            expect($check->is_up)->toBeTrue()
                ->and($check->status_code)->toBe(301);
        });

        it('records a 500 response as is_up=false', function (): void {
            Http::fake(['*' => Http::response('Error', 500)]);
            $monitor = Monitor::factory()->create();

            CheckMonitorJob::dispatchSync($monitor->id);

            $check = MonitorCheck::query()->first();
            expect($check->is_up)->toBeFalse()
                ->and($check->status_code)->toBe(500);
        });

        it('records a connection failure as status_code=0 with null response_time_ms', function (): void {
            Http::fake(function () {
                throw new ConnectionException('Connection refused');
            });
            $monitor = Monitor::factory()->create();

            CheckMonitorJob::dispatchSync($monitor->id);

            $check = MonitorCheck::query()->first();
            expect($check->status_code)->toBe(0)
                ->and($check->response_time_ms)->toBeNull()
                ->and($check->is_up)->toBeFalse();
        });
    });

    describe('threshold and status logic', function (): void {
        it('does not change status on a single failure when threshold is 3', function (): void {
            Http::fake(['*' => Http::response('Error', 500)]);
            $monitor = Monitor::factory()->create([
                'threshold' => 3,
                'status' => MonitorStatus::Pending,
                'consecutive_failures' => 0,
            ]);

            CheckMonitorJob::dispatchSync($monitor->id);

            expect($monitor->fresh()->status)->toBe(MonitorStatus::Pending)
                ->and($monitor->fresh()->consecutive_failures)->toBe(1);
        });

        it('increments consecutive_failures on each failed check', function (): void {
            Http::fake(['*' => Http::response('Error', 500)]);
            $monitor = Monitor::factory()->create([
                'threshold' => 5,
                'consecutive_failures' => 2,
            ]);

            CheckMonitorJob::dispatchSync($monitor->id);

            expect($monitor->fresh()->consecutive_failures)->toBe(3);
        });

        it('flips status to Down when consecutive failures reach threshold', function (): void {
            Http::fake(['*' => Http::response('Error', 500)]);
            $monitor = Monitor::factory()->create([
                'threshold' => 3,
                'consecutive_failures' => 2,
                'status' => MonitorStatus::Pending,
            ]);

            CheckMonitorJob::dispatchSync($monitor->id);

            expect($monitor->fresh()->status)->toBe(MonitorStatus::Down)
                ->and($monitor->fresh()->consecutive_failures)->toBe(3);
        });

        it('sets status to Up and resets failures on a successful check', function (): void {
            Http::fake(['*' => Http::response('OK', 200)]);
            $monitor = Monitor::factory()->create([
                'status' => MonitorStatus::Pending,
                'consecutive_failures' => 2,
            ]);

            CheckMonitorJob::dispatchSync($monitor->id);

            expect($monitor->fresh()->status)->toBe(MonitorStatus::Up)
                ->and($monitor->fresh()->consecutive_failures)->toBe(0);
        });

        it('recovers from Down to Up on a successful check', function (): void {
            Http::fake(['*' => Http::response('OK', 200)]);
            $monitor = Monitor::factory()->create([
                'status' => MonitorStatus::Down,
                'consecutive_failures' => 3,
            ]);

            CheckMonitorJob::dispatchSync($monitor->id);

            expect($monitor->fresh()->status)->toBe(MonitorStatus::Up)
                ->and($monitor->fresh()->consecutive_failures)->toBe(0);
        });
    });

    describe('uptime percentage and last_checked_at', function (): void {
        it('sets uptime_percentage to 100 after the first successful check', function (): void {
            Http::fake(['*' => Http::response('OK', 200)]);
            $monitor = Monitor::factory()->create();

            CheckMonitorJob::dispatchSync($monitor->id);

            expect((float) $monitor->fresh()->uptime_percentage)->toBe(100.0);
        });

        it('sets uptime_percentage to 0 after the first failed check', function (): void {
            Http::fake(['*' => Http::response('Error', 500)]);
            $monitor = Monitor::factory()->create(['threshold' => 5]);

            CheckMonitorJob::dispatchSync($monitor->id);

            expect((float) $monitor->fresh()->uptime_percentage)->toBe(0.0);
        });

        it('recalculates uptime_percentage across mixed checks', function (): void {
            $monitor = Monitor::factory()->create(['threshold' => 10]);
            MonitorCheck::factory()->for($monitor)->up()->count(2)->create();

            Http::fake(['*' => Http::response('Error', 500)]);
            CheckMonitorJob::dispatchSync($monitor->id);

            expect((float) $monitor->fresh()->uptime_percentage)->toBe(66.67);
        });

        it('updates last_checked_at after each check', function (): void {
            Http::fake(['*' => Http::response('OK', 200)]);
            $monitor = Monitor::factory()->create(['last_checked_at' => null]);

            CheckMonitorJob::dispatchSync($monitor->id);

            expect($monitor->fresh()->last_checked_at)->not->toBeNull();
        });
    });

    describe('notifications', function (): void {
        beforeEach(function (): void {
            Notification::fake();
            Config::set('services.monitor.alert_email', 'alert@example.com');
        });

        it('sends SiteDownNotification when monitor transitions from Pending to Down', function (): void {
            Http::fake(['*' => Http::response('Error', 500)]);
            $monitor = Monitor::factory()->create([
                'threshold' => 1,
                'status' => MonitorStatus::Pending,
                'consecutive_failures' => 0,
            ]);

            CheckMonitorJob::dispatchSync($monitor->id);

            Notification::assertSentOnDemand(SiteDownNotification::class);
        });

        it('sends SiteDownNotification when monitor transitions from Up to Down', function (): void {
            Http::fake(['*' => Http::response('Error', 500)]);
            $monitor = Monitor::factory()->create([
                'threshold' => 1,
                'status' => MonitorStatus::Up,
                'consecutive_failures' => 0,
            ]);

            CheckMonitorJob::dispatchSync($monitor->id);

            Notification::assertSentOnDemand(SiteDownNotification::class);
        });

        it('sends SiteUpNotification when monitor transitions from Down to Up', function (): void {
            Http::fake(['*' => Http::response('OK', 200)]);
            $monitor = Monitor::factory()->create(['status' => MonitorStatus::Down]);

            CheckMonitorJob::dispatchSync($monitor->id);

            Notification::assertSentOnDemand(SiteUpNotification::class);
        });

        it('does not send a notification when the monitor stays Up', function (): void {
            Http::fake(['*' => Http::response('OK', 200)]);
            $monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);

            CheckMonitorJob::dispatchSync($monitor->id);

            Notification::assertNothingSent();
        });

        it('does not send a notification when the monitor stays Down', function (): void {
            Http::fake(['*' => Http::response('Error', 500)]);
            $monitor = Monitor::factory()->create([
                'threshold' => 1,
                'status' => MonitorStatus::Down,
                'consecutive_failures' => 1,
            ]);

            CheckMonitorJob::dispatchSync($monitor->id);

            Notification::assertNothingSent();
        });

        it('does not send a notification when failures are below threshold', function (): void {
            Http::fake(['*' => Http::response('Error', 500)]);
            $monitor = Monitor::factory()->create([
                'threshold' => 3,
                'consecutive_failures' => 0,
                'status' => MonitorStatus::Pending,
            ]);

            CheckMonitorJob::dispatchSync($monitor->id);

            Notification::assertNothingSent();
        });

        it('does not send a notification on Pending to Up transition', function (): void {
            Http::fake(['*' => Http::response('OK', 200)]);
            $monitor = Monitor::factory()->create(['status' => MonitorStatus::Pending]);

            CheckMonitorJob::dispatchSync($monitor->id);

            Notification::assertNothingSent();
        });

        it('does not send a notification when MONITOR_ALERT_EMAIL is not configured', function (): void {
            Http::fake(['*' => Http::response('Error', 500)]);
            Config::set('services.monitor.alert_email', null);
            $monitor = Monitor::factory()->create([
                'threshold' => 1,
                'status' => MonitorStatus::Pending,
                'consecutive_failures' => 0,
            ]);

            CheckMonitorJob::dispatchSync($monitor->id);

            Notification::assertNothingSent();
        });
    });

    describe('guard clauses', function (): void {
        it('does nothing when the monitor has been deleted before the job runs', function (): void {
            CheckMonitorJob::dispatchSync(999);

            expect(MonitorCheck::count())->toBe(0);
        });
    });
});
