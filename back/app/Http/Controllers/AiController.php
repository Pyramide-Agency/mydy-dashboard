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

        $text = (new AiService())->complete($system, [['role' => 'user', 'content' => $prompt]]);

        return response()->json(['analysis' => $text]);
    }

    public function getConversation(): JsonResponse
    {
        $conversation = AiConversation::firstOrCreate(
            ['context_type' => 'finance_advisor'],
            ['messages'     => []]
        );

        return response()->json(['messages' => $conversation->messages]);
    }

    public function sendMessage(Request $request): StreamedResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $conversation = AiConversation::firstOrCreate(
            ['context_type' => 'finance_advisor'],
            ['messages'     => []]
        );

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
            $fullResponse = $ai->stream($systemPrompt, $messages, 2048, function ($chunk) {
                echo 'data: ' . json_encode(['chunk' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
                ob_flush();
                flush();
            });

            $updatedMessages   = $conversation->fresh()->messages ?? [];
            $updatedMessages[] = ['role' => 'user',      'content' => $userMessage];
            $updatedMessages[] = ['role' => 'assistant', 'content' => $fullResponse];

            if (count($updatedMessages) > 100) {
                $updatedMessages = array_slice($updatedMessages, -100);
            }

            $conversation->update(['messages' => $updatedMessages]);

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }
}
