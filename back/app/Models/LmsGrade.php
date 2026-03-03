<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsGrade extends Model
{
    protected $table = 'lms_grades';

    protected $fillable = [
        'course_id', 'current_score', 'final_score', 'current_grade',
        'final_grade', 'current_points', 'final_points',
    ];

    protected $casts = [
        'current_points' => 'decimal:2',
        'final_points'   => 'decimal:2',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }
}
