<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitorCheck>
 */
class MonitorCheckFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $statusCode = $this->faker->randomElement([200, 200, 200, 301, 404, 500, 503]);
        $isUp = $statusCode >= 200 && $statusCode < 400;

        return [
            'monitor_id' => Monitor::factory(),
            'status_code' => $statusCode,
            'response_time_ms' => $isUp ? $this->faker->numberBetween(50, 2000) : null,
            'is_up' => $isUp,
            'checked_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    public function up(): static
    {
        return $this->state(fn () => [
            'status_code' => 200,
            'response_time_ms' => $this->faker->numberBetween(50, 500),
            'is_up' => true,
        ]);
    }

    public function down(): static
    {
        return $this->state(fn () => [
            'status_code' => 500,
            'response_time_ms' => null,
            'is_up' => false,
        ]);
    }

    public function timedOut(): static
    {
        return $this->state(fn () => [
            'status_code' => 0,
            'response_time_ms' => null,
            'is_up' => false,
        ]);
    }
}
