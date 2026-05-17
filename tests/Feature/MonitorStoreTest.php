<?php

use App\Enums\MonitorStatus;
use App\Models\Monitor;

describe('POST /api/v1/monitors', function (): void {
    it('creates a monitor and returns 201', function (): void {
        $response = $this->postJson('/api/v1/monitors', [
            'url' => 'https://example.com',
            'check_interval' => 10,
            'threshold' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['status', 'message', 'data'])
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.url', 'https://example.com')
            ->assertJsonPath('data.check_interval', 10)
            ->assertJsonPath('data.threshold', 2)
            ->assertJsonPath('data.status', MonitorStatus::Pending->value);

        $this->assertDatabaseHas('monitors', [
            'url' => 'https://example.com',
            'check_interval' => 10,
            'threshold' => 2,
        ]);
    });

    it('applies default values when optional fields are omitted', function (): void {
        $this->postJson('/api/v1/monitors', [
            'url' => 'https://example.com',
        ])->assertStatus(201);

        $this->assertDatabaseHas('monitors', [
            'url' => 'https://example.com',
            'check_interval' => 5,
            'threshold' => 3,
        ]);
    });

    it('requires a url', function (): void {
        $this->postJson('/api/v1/monitors', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    });

    it('rejects a non-http url', function (): void {
        $this->postJson('/api/v1/monitors', [
            'url' => 'ftp://example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    });

    it('rejects a duplicate url', function (): void {
        Monitor::factory()->create(['url' => 'https://example.com']);

        $this->postJson('/api/v1/monitors', [
            'url' => 'https://example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    });

    it('rejects check_interval below 1', function (): void {
        $this->postJson('/api/v1/monitors', [
            'url' => 'https://example.com',
            'check_interval' => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['check_interval']);
    });

    it('rejects check_interval above 60', function (): void {
        $this->postJson('/api/v1/monitors', [
            'url' => 'https://example.com',
            'check_interval' => 61,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['check_interval']);
    });

    it('rejects threshold below 1', function (): void {
        $this->postJson('/api/v1/monitors', [
            'url' => 'https://example.com',
            'threshold' => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['threshold']);
    });
});
