<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelanceSession extends Model
{
    protected $fillable = [
        'project_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'pause_started_at',
        'total_paused_seconds',
        'note',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
        'pause_started_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FreelanceProject::class, 'project_id');
    }

    /**
     * Scope to get only active (not ended) sessions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Calculate elapsed seconds for this session.
     * If paused: total time - paused time (including current pause duration)
     * If running: total time - paused time
     */
    public function getElapsedSeconds(): int
    {
        $totalPaused = $this->total_paused_seconds ?? 0;

        // If currently paused, add the current pause duration to total paused
        if ($this->pause_started_at) {
            $totalPaused += (int) $this->pause_started_at->diffInSeconds(now());
        }

        $totalElapsed = (int) $this->started_at->diffInSeconds(now());

        return max(0, $totalElapsed - $totalPaused);
    }
}
