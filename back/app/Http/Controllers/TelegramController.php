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
        $data  = $request->validate(['token' => 'required|string']);
        $token = $data['token'];

        // Step 1: validate token independently of webhook URL
        $me = Http::get("https://api.telegram.org/bot{$token}/getMe");
        if (!$me->ok() || !$me->json('ok')) {
            return $this->error('Неверный токен бота. Проверьте токен от @BotFather.', 422);
        }

        // Step 2: try to register webhook (may fail if app URL is not HTTPS/public)
        $baseUrl = config('app.url') ?: $request->getSchemeAndHttpHost();
        if (!str_starts_with($baseUrl, 'https://')) {
            $baseUrl = preg_replace('#^http://#', 'https://', $baseUrl);
        }
        $webhook  = rtrim($baseUrl, '/') . '/api/telegram/webhook';
        $whResult = Http::asJson()->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url'             => $webhook,
            'allowed_updates' => ['message'],
        ]);

        Log::info('Telegram webhook response', ['response' => $whResult->json()]);

        $botName = $me->json('result.username', 'бот');

        if (!$whResult->ok() || !$whResult->json('ok')) {
            $description = $whResult->json('description') ?: 'Неизвестная ошибка регистрации webhook';
            return $this->error("Не удалось зарегистрировать webhook: {$description}. Убедитесь, что приложение доступно по HTTPS и публично.", 422);
        }

        // Step 3: save token only after successful webhook registration
        Setting::set('telegram_bot_token', $token);

        return $this->success(
            ['message' => "Telegram бот @{$botName} подключён"],
            "Telegram бот подключён"
        );
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
