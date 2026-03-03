<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsAnnouncement extends Model
{
    protected $table = 'lms_announcements';

    protected $fillable = [
        'canvas_id', 'course_id', 'title', 'message', 'author',
        'author_avatar_url', 'posted_at', 'html_url', 'read',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'read'      => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }
}
