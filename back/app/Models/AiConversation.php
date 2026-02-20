<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $fillable = ['context_type', 'messages'];

    protected $casts = [
        'messages' => 'array',
    ];
}
