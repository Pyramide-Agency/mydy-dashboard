<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsCalendarEvent extends Model
{
    protected $table = 'lms_calendar_events';

    protected $fillable = [
        'canvas_id', 'course_id', 'title', 'description', 'start_at',
        'end_at', 'location', 'event_type', 'html_url',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }
}
