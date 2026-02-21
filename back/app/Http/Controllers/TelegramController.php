<?php

namespace App\Http\Controllers;

use App\Models\FinanceEntry;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string']);

        $token   = $data['token'];
        // $webhook = url('/api/telegram/webhook');
        $webhook = 'https://4171-84-54-76-217.ngrok-free.app/api/telegram/webhook';
        $response = Http::get("https://api.telegram.org/bot{$token}/setWebhook", [
            'url'             => $webhook,
            'allowed_updates' => ['message'],
        ]);

        Log::info('Telegram webhook response', ['response' => $response->json()]);

        if (!$response->ok() || !$response->json('ok')) {
            return response()->json(['message' => 'Неверный токен или ошибка Telegram'], 422);
        }

        Setting::set('telegram_bot_token', $token);

        return response()->json(['message' => 'Telegram бот подключён', 'webhook' => $webhook]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $update = $request->all();

        if (!isset($update['message'])) {
            return response()->json(['ok' => true]);
        }

        $message = $update['message'];
        $chatId  = $message['chat']['id'];
        $text    = trim($message['text'] ?? '');

        if (str_starts_with($text, '/add')) {
            $this->handleAdd($chatId, $text);
        } elseif (str_starts_with($text, '/today')) {
            $this->handleToday($chatId);
        } else {
            $this->handleHelp($chatId);
        }

        return response()->json(['ok' => true]);
    }

    private function handleAdd(int $chatId, string $text): void
    {
        if (!preg_match('/^\/add\s+([\d.]+)\s+(.+)$/u', $text, $matches)) {
            $this->sendMessage($chatId, "❌ Формат: /add 25.50 Описание");
            return;
        }

        $amount      = (float) $matches[1];
        $description = $matches[2];

        FinanceEntry::create([
            'amount'      => $amount,
            'description' => $description,
            'date'        => today(),
            'source'      => 'telegram',
        ]);

        $symbol  = Setting::get('currency_symbol', '$');
        $this->sendMessage($chatId, "✅ Добавлено: {$symbol}{$amount} — {$description}");
    }

    private function handleToday(int $chatId): void
    {
        $entries = FinanceEntry::with('category')
            ->whereDate('date', today())
            ->get();

        if ($entries->isEmpty()) {
            $this->sendMessage($chatId, "📊 Сегодня расходов нет.");
            return;
        }

        $symbol = Setting::get('currency_symbol', '$');
        $total  = $entries->sum('amount');
        $lines  = $entries->map(fn($e) =>
            "• {$symbol}" . number_format($e->amount, 2) .
            " — " . ($e->description ?? '') .
            " [" . ($e->category?->name ?? 'Без категории') . "]"
        )->join("\n");

        $this->sendMessage($chatId, "📊 Расходы за сегодня:\n{$lines}\n\n💰 Итого: {$symbol}" . number_format($total, 2));
    }

    private function handleHelp(int $chatId): void
    {
        $this->sendMessage($chatId,
            "🤖 Команды бота:\n\n" .
            "/add [сумма] [описание] — добавить расход\n" .
            "/today — расходы за сегодня\n" .
            "/help — список команд"
        );
    }

    private function sendMessage(int $chatId, string $text): void
    {
        $token = Setting::get('telegram_bot_token');
        if (!$token) return;

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text'    => $text,
        ]);
    }
}
