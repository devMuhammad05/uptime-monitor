<?php

use App\Enums\MonitorStatus;
use App\Models\Monitor;

describe('GET /api/v1/monitors', function (): void {
    it('returns 200 with an empty list when no monitors exist', function (): void {
        $this->getJson('/api/v1/monitors')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data', []);
    });

    it('returns all monitors with correct fields', function (): void {
        Monitor::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/monitors');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'url',
                        'check_interval',
                        'threshold',
                        'status',
                        'last_checked_at',
                        'uptime_percentage',
                        'created_at',
                    ],
                ],
            ]);
    });

    it('returns the correct status value for each monitor', function (): void {
        Monitor::factory()->create(['status' => MonitorStatus::Up]);
        Monitor::factory()->create(['status' => MonitorStatus::Down]);
        Monitor::factory()->create(['status' => MonitorStatus::Pending]);

        $response = $this->getJson('/api/v1/monitors');

        $statuses = collect($response->json('data'))->pluck('status')->sort()->values()->all();

        expect($statuses)->toBe(['down', 'pending', 'up']);
    });
});
