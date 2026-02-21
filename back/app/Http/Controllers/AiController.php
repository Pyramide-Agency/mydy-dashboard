<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Services\AiService;
use App\Services\FinanceContextBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiController extends Controller
{
    public function feedback(): JsonResponse
    {
        $ctx = (new FinanceContextBuilder())->buildForFeedback();

        if (empty($ctx['entries'])) {
            return response()->json(['analysis' => 'Сегодня расходов ещё нет. Начните добавлять расходы, чтобы получить анализ.']);
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
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['analysis' => $text]);
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

        return response()->json($conversations);
    }

    public function createConversation(): JsonResponse
    {
        $conv = AiConversation::create([
            'context_type' => 'finance_advisor',
            'title'        => 'Новый чат',
            'messages'     => [],
        ]);

        return response()->json([
            'id'       => $conv->id,
            'title'    => $conv->title,
            'messages' => [],
        ]);
    }

    public function deleteConversation(int $id): JsonResponse
    {
        AiConversation::findOrFail($id)->delete();

        return response()->json(['message' => 'Чат удалён']);
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

        return response()->json([
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

        $ctx          = (new FinanceContextBuilder())->buildForMessage($request->message);
        $systemPrompt = "Ты персональный финансовый советник. Русский язык. Будь кратким и конкретным.\n"
            . 'Финансовый контекст: ' . json_encode($ctx, JSON_UNESCAPED_UNICODE);

        $messages   = $conversation->messages;
        $messages[] = ['role' => 'user', 'content' => $request->message];

        if (count($messages) > 100) {
            $messages = array_slice($messages, -100);
        }

        $userMessage = $request->message;
        $ai          = new AiService();

        return response()->stream(function () use ($messages, $systemPrompt, $conversation, $userMessage, $ai) {
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

            echo "data: [DONE]\n\n";
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function preview(array $messages): string
    {
        $last = collect($messages)->last();
        return $last ? mb_substr($last['content'], 0, 80) : '';
    }
}
