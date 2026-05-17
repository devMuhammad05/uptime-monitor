<?php

use App\Models\Monitor;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

describe('Rate limiting', function (): void {
    it('throttles POST /api/monitors after 10 requests', function (): void {
        foreach (range(1, 10) as $i) {
            $this->postJson('/api/monitors', [
                'url' => "https://example{$i}.com",
            ])->assertStatus(201);
        }

        $this->postJson('/api/monitors', [
            'url' => 'https://example-extra.com',
        ])->assertStatus(429);
    });

    it('throttles GET /api/monitors after 60 requests', function (): void {
        foreach (range(1, 60) as $i) {
            $this->getJson('/api/monitors')->assertStatus(200);
        }

        $this->getJson('/api/monitors')->assertStatus(429);
    });

    it('throttles GET /api/monitors/{id}/history after 60 requests', function (): void {
        $monitor = Monitor::factory()->create();

        foreach (range(1, 60) as $i) {
            $this->getJson("/api/monitors/{$monitor->id}/history")->assertStatus(200);
        }

        $this->getJson("/api/monitors/{$monitor->id}/history")->assertStatus(429);
    });
});
