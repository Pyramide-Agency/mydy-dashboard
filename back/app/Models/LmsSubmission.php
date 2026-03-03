<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsSubmission extends Model
{
    protected $table = 'lms_submissions';

    protected $fillable = [
        'canvas_id', 'assignment_id', 'workflow_state', 'score', 'grade',
        'grade_str', 'submitted_at', 'graded_at', 'late', 'missing',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at'    => 'datetime',
        'score'        => 'decimal:2',
        'grade'        => 'decimal:2',
        'late'         => 'boolean',
        'missing'      => 'boolean',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LmsAssignment::class, 'assignment_id');
    }
}
