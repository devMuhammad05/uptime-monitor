<?php

use App\Models\Monitor;
use App\Models\MonitorCheck;

describe('GET /api/v1/monitors/{id}/history', function (): void {
    it('returns 404 when monitor does not exist', function (): void {
        $this->getJson('/api/v1/monitors/999/history')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Monitor not found.');
    });

    it('returns 200 with empty data when monitor has no checks', function (): void {
        $monitor = Monitor::factory()->create();

        $this->getJson("/api/v1/monitors/{$monitor->id}/history")
            ->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 0);
    });

    it('returns checks with the correct fields', function (): void {
        $monitor = Monitor::factory()->create();
        MonitorCheck::factory()->for($monitor)->create([
            'status_code' => 200,
            'response_time_ms' => 245,
            'is_up' => true,
            'checked_at' => '2026-05-13 10:05:00',
        ]);

        $this->getJson("/api/v1/monitors/{$monitor->id}/history")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'monitor_id', 'status_code', 'response_time_ms', 'is_up', 'checked_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.status_code', 200)
            ->assertJsonPath('data.0.response_time_ms', 245)
            ->assertJsonPath('data.0.is_up', true);
    });

    it('returns checks ordered by checked_at descending', function (): void {
        $monitor = Monitor::factory()->create();

        MonitorCheck::factory()->for($monitor)->create(['checked_at' => '2026-05-13 10:00:00']);
        MonitorCheck::factory()->for($monitor)->create(['checked_at' => '2026-05-13 10:10:00']);
        MonitorCheck::factory()->for($monitor)->create(['checked_at' => '2026-05-13 10:05:00']);

        $response = $this->getJson("/api/v1/monitors/{$monitor->id}/history");

        $checkedAts = collect($response->json('data'))->pluck('checked_at')->all();

        expect($checkedAts[0])->toBeGreaterThan($checkedAts[1])
            ->and($checkedAts[1])->toBeGreaterThan($checkedAts[2]);
    });

    it('returns correct meta for paginated results', function (): void {
        $monitor = Monitor::factory()->create();
        MonitorCheck::factory()->for($monitor)->count(20)->create();

        $this->getJson("/api/v1/monitors/{$monitor->id}/history?per_page=5")
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.current_page', 1);
    });

    it('respects the page query parameter', function (): void {
        $monitor = Monitor::factory()->create();
        MonitorCheck::factory()->for($monitor)->count(20)->create();

        $this->getJson("/api/v1/monitors/{$monitor->id}/history?per_page=5&page=2")
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    });

    it('caps per_page at 100', function (): void {
        $monitor = Monitor::factory()->create();
        MonitorCheck::factory()->for($monitor)->count(10)->create();

        $this->getJson("/api/v1/monitors/{$monitor->id}/history?per_page=200")
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 100);
    });

    it('uses 15 as default per_page', function (): void {
        $monitor = Monitor::factory()->create();

        $this->getJson("/api/v1/monitors/{$monitor->id}/history")
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 15);
    });

    it('represents a timed-out check with status_code 0 and null response_time_ms', function (): void {
        $monitor = Monitor::factory()->create();
        MonitorCheck::factory()->for($monitor)->timedOut()->create();

        $this->getJson("/api/v1/monitors/{$monitor->id}/history")
            ->assertStatus(200)
            ->assertJsonPath('data.0.status_code', 0)
            ->assertJsonPath('data.0.response_time_ms', null)
            ->assertJsonPath('data.0.is_up', false);
    });

    it('only returns checks for the requested monitor', function (): void {
        $monitor = Monitor::factory()->create();
        $other = Monitor::factory()->create();

        MonitorCheck::factory()->for($monitor)->count(2)->create();
        MonitorCheck::factory()->for($other)->count(5)->create();

        $this->getJson("/api/v1/monitors/{$monitor->id}/history")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    });
});
