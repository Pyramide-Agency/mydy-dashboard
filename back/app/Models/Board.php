<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    protected $fillable = ['name', 'description', 'is_default'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function columns(): HasMany
    {
        return $this->hasMany(Column::class)->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
