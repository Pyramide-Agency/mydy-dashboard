<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreelanceProject extends Model
{
    protected $fillable = [
        'name',
        'color',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(FreelanceSession::class, 'project_id');
    }
}
