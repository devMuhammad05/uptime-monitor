<?php

namespace App\Actions;

use App\Models\Monitor;

class CalculateUptimeAction
{
    public function execute(Monitor $monitor): float
    {
        $result = $monitor->checks()
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_up THEN 1 ELSE 0 END) as up_count')
            ->first();

        if (! $result || (int) $result->total === 0) {
            return 0.0;
        }

        return round($result->up_count / $result->total * 100, 2);
    }
}
