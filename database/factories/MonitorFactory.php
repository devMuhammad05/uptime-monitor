<?php

namespace Database\Factories;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'url' => 'https://'.$this->faker->unique()->domainName(),
            'check_interval' => 5,
            'threshold' => 3,
            'status' => MonitorStatus::Pending,
            'consecutive_failures' => 0,
            'last_checked_at' => null,
            'uptime_percentage' => null,
        ];
    }
}
