<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WorkSession extends Model
{
    protected $fillable = [
        'checked_in_at',
        'checked_out_at',
        'duration_minutes',
        'note',
    ];

    protected $casts = [
        'checked_in_at'  => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    /**
     * Returns the currently open session (no checkout), or null.
     */
    public static function currentOpen(): ?self
    {
        return static::whereNull('checked_out_at')
            ->orderByDesc('checked_in_at')
            ->first();
    }

    /**
     * Close this session: record checkout time and compute duration.
     */
    public function checkout(Carbon $at = null): void
    {
        $at = $at ?? now();
        $this->checked_out_at   = $at;
        $this->duration_minutes = (int) $this->checked_in_at->diffInMinutes($at);
        $this->save();
    }
}
