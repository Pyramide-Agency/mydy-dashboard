<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMemory;
use App\Services\AiService;
use App\Services\FinanceContextBuilder;
use App\Services\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiController extends Controller
{
    public function feedback(): JsonResponse
    {
        $ctx = (new FinanceContextBuilder())->buildForFeedback();

        if (empty($ctx['entries'])) {
            return $this->success(['analysis' => 'Сегодня расходов ещё нет. Начните добавлять расходы, чтобы получить анализ.']);
        }

        $c      = $ctx['currency'];
        $system = 'Ты финансовый аналитик. Отвечай на русском языке, кратко и структурированно.';
        $prompt = "Расходы за {$ctx['date']} ({$c}):\n"
            . json_encode($ctx['entries'], JSON_UNESCAPED_UNICODE) . "\n"
            . "Итого: {$c}{$ctx['today_expense']} | Доходы: {$c}{$ctx['today_income']}"
            . ($ctx['yesterday_expense'] > 0 ? " | Вчера расходы: {$c}{$ctx['yesterday_expense']}" : '') . "\n"
            . "По категориям: " . json_encode($ctx['by_cat'], JSON_UNESCAPED_UNICODE) . "\n\n"
            . "Дай краткий анализ (4-6 пунктов).";

        try {
            $text = (new AiService())->complete($system, [['role' => 'user', 'content' => $prompt]]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }

        return $this->success(['analysis' => $text]);
    }

    // ─── Conversation CRUD ────────────────────────────────────────────────────

    public function listConversations(): JsonResponse
    {
        $conversations = AiConversation::orderByDesc('updated_at')
            ->get()
            ->map(fn ($c) => [
                'id'         => $c->id,
                'title'      => $c->title,
                'updated_at' => $c->updated_at,
                'preview'    => $this->preview($c->messages),
            ]);

        return $this->success($conversations->toArray());
    }

    public function createConversation(): JsonResponse
    {
        $conv = AiConversation::create([
            'context_type' => 'finance_advisor',
            'title'        => 'Новый чат',
            'messages'     => [],
        ]);

        return $this->success([
            'id'       => $conv->id,
            'title'    => $conv->title,
            'messages' => [],
        ], 'Чат создан', 201);
    }

    public function deleteConversation(int $id): JsonResponse
    {
        AiConversation::findOrFail($id)->delete();

        return $this->success(message: 'Чат удалён');
    }

    public function getConversation(Request $request): JsonResponse
    {
        $conv = $request->query('id')
            ? AiConversation::findOrFail($request->query('id'))
            : AiConversation::orderByDesc('updated_at')->first()
              ?? AiConversation::create([
                    'context_type' => 'finance_advisor',
                    'title'        => 'Новый чат',
                    'messages'     => [],
                 ]);

        return $this->success([
            'id'       => $conv->id,
            'title'    => $conv->title,
            'messages' => $conv->messages,
        ]);
    }

    // ─── Streaming ────────────────────────────────────────────────────────────

    public function sendMessage(Request $request): StreamedResponse
    {
        $request->validate([
            'message'         => 'required|string|max:2000',
            'conversation_id' => 'sometimes|integer|exists:ai_conversations,id',
        ]);

        $conversation = $request->filled('conversation_id')
            ? AiConversation::findOrFail($request->input('conversation_id'))
            : AiConversation::orderByDesc('updated_at')->first()
              ?? AiConversation::create([
                    'context_type' => 'finance_advisor',
                    'title'        => 'Новый чат',
                    'messages'     => [],
                 ]);

        $memoryService = new MemoryService();
        $financeCtx    = (new FinanceContextBuilder())->buildForMessage($request->message);

        // Retrieve relevant memories to include in system prompt
        $memories     = $memoryService->search($request->message);
        $systemPrompt = "Ты умный персональный ассистент. Отвечай на том языке, на котором пишет пользователь. Будь полезным, кратким и конкретным."
            . "\n\nФинансовые данные пользователя (реальные, актуальные):\n" . json_encode($financeCtx, JSON_UNESCAPED_UNICODE);

        if (! empty($memories)) {
            $systemPrompt .= "\n\nПамять о пользователе:\n- " . implode("\n- ", $memories);
        }

        $messages   = $conversation->messages;
        $messages[] = ['role' => 'user', 'content' => $request->message];

        if (count($messages) > 100) {
            $messages = array_slice($messages, -100);
        }

        $userMessage = $request->message;
        $ai          = new AiService();

        return response()->stream(function () use ($messages, $systemPrompt, $conversation, $userMessage, $ai, $memoryService) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            ob_implicit_flush(true);

            $fullResponse = '';
            try {
                $fullResponse = $ai->stream($systemPrompt, $messages, 2048, function ($chunk) {
                    echo 'data: ' . json_encode(['chunk' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
                });
            } catch (\Throwable $e) {
                echo 'data: ' . json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . "\n\n";
            }

            try {
                $updatedMessages   = $conversation->fresh()->messages ?? [];
                $updatedMessages[] = ['role' => 'user',      'content' => $userMessage];
                $updatedMessages[] = ['role' => 'assistant', 'content' => $fullResponse];

                if (count($updatedMessages) > 100) {
                    $updatedMessages = array_slice($updatedMessages, -100);
                }

                // Auto-title: set to first user message if still default
                $newTitle = $conversation->fresh()->title ?? 'Новый чат';
                if ($newTitle === 'Новый чат') {
                    $newTitle = mb_substr($userMessage, 0, 50);
                }

                $conversation->update(['messages' => $updatedMessages, 'title' => $newTitle]);
            } catch (\Throwable) {}

            // Extract and store memories from this exchange (async-ish, after response sent)
            if (! empty($fullResponse)) {
                $memoryService->extractAndStore($userMessage, $fullResponse);
            }

            echo "data: [DONE]\n\n";
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    // ─── Memory endpoints ─────────────────────────────────────────────────────

    public function listMemories(): JsonResponse
    {
        $memories = AiMemory::select('id', 'content', 'category', 'created_at')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($memories->toArray());
    }

    public function storeMemory(Request $request): JsonResponse
    {
        $request->validate(['content' => 'required|string|max:1000']);

        (new MemoryService())->store($request->input('content'));

        return $this->success(message: 'Сохранено');
    }

    public function deleteMemory(int $id): JsonResponse
    {
        AiMemory::findOrFail($id)->delete();

        return $this->success(message: 'Удалено');
    }

    public function clearMemories(): JsonResponse
    {
        AiMemory::truncate();

        return $this->success(message: 'Память очищена');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function preview(array $messages): string
    {
        $last = collect($messages)->last();
        return $last ? mb_substr($last['content'], 0, 80) : '';
    }
}
