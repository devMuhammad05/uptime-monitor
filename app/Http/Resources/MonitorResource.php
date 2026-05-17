<?php

namespace App\Http\Resources;

use App\Enums\MonitorStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var MonitorStatus $status */
        $status = $this->status;

        return [
            'id' => $this->id,
            'url' => $this->url,
            'check_interval' => $this->check_interval,
            'threshold' => $this->threshold,
            'status' => $status->value,
            'consecutive_failures' => $this->consecutive_failures,
            'uptime_percentage' => $this->uptime_percentage,
            'last_checked_at' => $this->last_checked_at,
            'created_at' => $this->created_at,
        ];
    }
}
