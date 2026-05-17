<?php

namespace App\Models;

use App\Enums\MonitorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Monitor extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'url',
        'check_interval',
        'threshold',
        'status',
        'consecutive_failures',
        'last_checked_at',
        'uptime_percentage',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => MonitorStatus::class,
        'last_checked_at' => 'datetime',
        'uptime_percentage' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
