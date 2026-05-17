<?php

namespace App\Models;

use App\Enums\MonitorStatus;
use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use HasFactory;

    /** @var array<string, int|string> */
    protected $attributes = [
        'status' => 'pending',
        'check_interval' => 5,
        'threshold' => 3,
        'consecutive_failures' => 0,
    ];

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
