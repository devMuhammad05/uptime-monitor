<?php

namespace App\Actions;

use App\Models\Monitor;

class CalculateUptimeAction
{
    public function execute(Monitor $monitor): float
    {
        $total = $monitor->checks()->count();

        if ($total === 0) {
            return 0.0;
        }

        $upCount = $monitor->checks()->where('is_up', true)->count();

        return round($upCount / $total * 100, 2);
    }
}
