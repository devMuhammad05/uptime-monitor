<?php

namespace App\Actions;

use App\Http\Resources\MonitorCheckResource;
use App\Models\Monitor;
use Illuminate\Http\Request;

class GetMonitorHistoryAction
{
    /** @return array{data: array<int, mixed>, meta: array{current_page: int, per_page: int, total: int}} */
    public function execute(Monitor $monitor, Request $request): array
    {
        $perPage = min(max(1, (int) $request->query('per_page', 15)), 100);

        $paginator = $monitor->checks()
            ->latest('checked_at')
            ->paginate($perPage);

        return [
            'data' => MonitorCheckResource::collection($paginator->items())->toArray($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
