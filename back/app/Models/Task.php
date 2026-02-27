<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'board_id', 'column_id', 'title', 'description',
        'priority', 'deadline', 'archived', 'archived_at', 'position',
    ];

    protected $casts = [
        'archived'    => 'boolean',
        'archived_at' => 'datetime',
        'deadline'    => 'datetime',
        'position'    => 'integer',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }
}
