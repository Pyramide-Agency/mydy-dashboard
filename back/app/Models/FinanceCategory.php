<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCategory extends Model
{
    protected $fillable = ['name', 'color', 'icon'];

    public function entries(): HasMany
    {
        return $this->hasMany(FinanceEntry::class, 'category_id');
    }
}
