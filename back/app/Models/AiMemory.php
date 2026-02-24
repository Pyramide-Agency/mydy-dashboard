<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AiMemory extends Model
{
    protected $fillable = ['content', 'category', 'embedding'];

    protected $casts = [
        'embedding' => 'array',
    ];

    /**
     * Cosine similarity search using pgvector.
     * Returns the top $limit most similar memories as plain arrays.
     */
    public static function similarTo(array $vector, int $limit = 5): array
    {
        $vectorStr = '[' . implode(',', $vector) . ']';

        return DB::select(
            "SELECT id, content, category, created_at,
                    1 - (embedding <=> ?) AS similarity
             FROM ai_memories
             WHERE embedding IS NOT NULL
             ORDER BY embedding <=> ?
             LIMIT ?",
            [$vectorStr, $vectorStr, $limit]
        );
    }
}
