<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LmsAssignment extends Model
{
    protected $table = 'lms_assignments';

    protected $fillable = [
        'canvas_id', 'course_id', 'name', 'description', 'due_at',
        'lock_at', 'points_possible', 'submission_types', 'assignment_type',
        'workflow_state', 'html_url',
    ];

    protected $casts = [
        'due_at'          => 'datetime',
        'lock_at'         => 'datetime',
        'points_possible' => 'decimal:2',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function submission(): HasOne
    {
        return $this->hasOne(LmsSubmission::class, 'assignment_id');
    }
}
