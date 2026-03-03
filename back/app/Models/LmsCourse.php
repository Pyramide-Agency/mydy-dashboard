<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LmsCourse extends Model
{
    protected $table = 'lms_courses';

    protected $fillable = [
        'canvas_id', 'name', 'course_code', 'description', 'instructor',
        'workflow_state', 'start_at', 'end_at', 'image_download_url', 'color',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(LmsAssignment::class, 'course_id');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(LmsAnnouncement::class, 'course_id');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(LmsCalendarEvent::class, 'course_id');
    }

    public function grade(): HasOne
    {
        return $this->hasOne(LmsGrade::class, 'course_id');
    }
}
