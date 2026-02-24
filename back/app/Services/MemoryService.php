<?php

namespace App\Services;

use App\Models\AiMemory;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MemoryService
{
    private const JINA_ENDPOINT = 'https://api.jina.ai/v1/embeddings';
    private const JINA_MODEL    = 'jina-embeddings-v3';
    private const DIMS          = 1024;

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Get a vector embedding from Jina AI for the given text.
     */
    public function embed(string $text): array
    {
        $apiKey = Setting::get('jina_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('Jina API key not configured (jina_api_key)');
        }

        $response = Http::withToken($apiKey)
            ->post(self::JINA_ENDPOINT, [
                'model'             => self::JINA_MODEL,
                'input'             => [$text],
                'dimensions'        => self::DIMS,
                'task'              => 'text-matching',
                'normalized'        => true,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Jina embedding error: ' . $response->body());
        }

        return $response->json('data.0.embedding') ?? [];
    }

    /**
     * Search for the most relevant memories for a given query string.
     * Uses cosine similarity if Jina key is configured, otherwise returns latest records.
     * Returns array of content strings.
     */
    public function search(string $query, int $limit = 5): array
    {
        if (AiMemory::count() === 0) {
            return [];
        }

        // Try vector search if Jina is configured
        if (Setting::get('jina_api_key')) {
            try {
                $vector  = $this->embed($query);
                $results = AiMemory::similarTo($vector, $limit);
                return array_map(fn ($row) => $row->content, $results);
            } catch (\Throwable $e) {
                Log::warning('MemoryService::search (vector) failed: ' . $e->getMessage());
            }
        }

        // Fallback: return most recent memories
        return AiMemory::orderByDesc('created_at')
            ->limit($limit)
            ->pluck('content')
            ->toArray();
    }

    /**
     * Store a new memory fact. Embeds via Jina if key is configured, otherwise stores text only.
     */
    public function store(string $content): void
    {
        try {
            if (Setting::get('jina_api_key')) {
                $embedding = $this->embed($content);
                $vectorStr = '[' . implode(',', $embedding) . ']';

                \Illuminate\Support\Facades\DB::statement(
                    'INSERT INTO ai_memories (content, embedding, created_at, updated_at) VALUES (?, ?::vector, NOW(), NOW())',
                    [$content, $vectorStr]
                );
            } else {
                AiMemory::create(['content' => $content]);
            }
        } catch (\Throwable $e) {
            Log::warning('MemoryService::store failed: ' . $e->getMessage());
        }
    }

    /**
     * Use AI to extract memorable facts from a user/assistant exchange,
     * then store each one.
     */
    public function extractAndStore(string $userMsg, string $assistantReply): void
    {
        try {
            $groqKey = Setting::get('groq_api_key');
            if (! $groqKey) {
                // Fall back to main AI key if using groq provider
                $groqKey = Setting::get('ai_api_key');
            }
            if (! $groqKey) {
                return;
            }

            $prompt = "Ты — система памяти. Проанализируй следующий диалог и извлеки конкретные факты о пользователе (предпочтения, цели, привычки, личные данные). НЕ извлекай общие финансовые советы или абстрактные утверждения — только персональные факты об этом конкретном человеке.\n\nПользователь: {$userMsg}\nАссистент: {$assistantReply}\n\nОтветь ТОЛЬКО валидным JSON массивом строк. Если нечего запомнить — верни пустой массив []. Примеры хорошего факта: \"Пользователь хочет накопить на машину\", \"Пользователь тратит много на кофе\". Не более 3 фактов.";

            $response = Http::withToken($groqKey)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'      => 'llama-3.3-70b-versatile',
                    'messages'   => [
                        ['role' => 'system', 'content' => 'You extract personal facts about users from conversations. Always respond with valid JSON only.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens'  => 256,
                    'temperature' => 0.1,
                ]);

            if ($response->failed()) {
                return;
            }

            $text  = $response->json('choices.0.message.content', '');
            // Extract JSON array from the response (in case there is extra text)
            if (preg_match('/\[.*\]/s', $text, $m)) {
                $facts = json_decode($m[0], true);
            } else {
                $facts = json_decode($text, true);
            }

            if (! is_array($facts)) {
                return;
            }

            foreach ($facts as $fact) {
                if (is_string($fact) && strlen(trim($fact)) > 0) {
                    $this->store(trim($fact));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('MemoryService::extractAndStore failed: ' . $e->getMessage());
        }
    }
}
