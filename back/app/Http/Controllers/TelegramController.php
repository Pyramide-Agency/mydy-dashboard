<?php

namespace App\Http\Controllers;

use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\Setting;
use App\Services\AiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    // ── Webhook entry point ───────────────────────────────────────────────────

    public function webhook(Request $request): JsonResponse
    {
        $update = $request->all();

        // Inline-keyboard button press
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
            return response()->json(['ok' => true]);
        }

        if (!isset($update['message'])) {
            return response()->json(['ok' => true]);
        }

        $message = $update['message'];
        $chatId  = $message['chat']['id'];
        $text    = trim($message['text'] ?? '');

        if (empty($text)) {
            return response()->json(['ok' => true]);
        }

        // If we're waiting for the user to type an edited field value
        $pending = $this->getPending();
        if ($pending && !empty($pending['editing'])) {
            $this->handleEditResponse($chatId, $text, $pending);
            return response()->json(['ok' => true]);
        }

        // Explicit commands
        if (str_starts_with($text, '/start')) {
            $this->handleStart($chatId);
        } elseif (str_starts_with($text, '/add')) {
            $this->handleAdd($chatId, $text);
        } elseif (str_starts_with($text, '/today')) {
            $this->handleToday($chatId);
        } elseif (str_starts_with($text, '/help')) {
            $this->handleHelp($chatId);
        } else {
            // Natural language → AI smart parse
            $this->handleSmartAdd($chatId, $text);
        }

        return response()->json(['ok' => true]);
    }

    // ── Smart AI parsing ──────────────────────────────────────────────────────

    private function handleSmartAdd(int $chatId, string $text): void
    {
        $this->sendChatAction($chatId, 'typing');

        try {
            $parsed = $this->parseTxWithAi($text);
        } catch (\Throwable $e) {
            Log::error('Telegram smart parse failed', ['error' => $e->getMessage()]);
            $this->sendMessage($chatId,
                "⚠️ Не удалось распознать запись.\n\nПопробуйте команду:\n/add 60000 Описание"
            );
            return;
        }

        if (!$parsed || empty($parsed['amount'])) {
            $this->sendMessage($chatId,
                "🤔 Не понял. Опишите иначе или используйте:\n/add 60000 Описание"
            );
            return;
        }

        $category = $this->resolveCategory($parsed['category'] ?? '');

        // Parse date from AI response (may be null → today)
        $date = !empty($parsed['date'])
            ? $this->parseDate($parsed['date'])
            : today()->toDateString();

        $pending = [
            'chat_id'     => $chatId,
            'type'        => $parsed['type'] ?? 'expense',
            'amount'      => (float) ($parsed['amount'] ?? 0),
            'description' => $parsed['description'] ?? '',
            'category'    => $category?->name ?? 'Без категории',
            'category_id' => $category?->id,
            'date'        => $date,
            'editing'     => null,
            'message_id'  => null,
        ];

        $this->savePending($pending);

        $msgId = $this->sendConfirmationMessage($chatId, $pending);

        $pending['message_id'] = $msgId;
        $this->savePending($pending);
    }

    private function parseTxWithAi(string $text): ?array
    {
        $categories = FinanceCategory::pluck('name')->join(', ');
        $symbol     = Setting::get('currency_symbol', '$');
        $today      = today()->toDateString();

        $system = 'Ты парсер финансовых записей. Из текста извлеки данные и верни ТОЛЬКО валидный JSON без пояснений.';
        $prompt = "Сегодня: {$today}\n"
            . "Доступные категории: {$categories}\n"
            . "Валюта: {$symbol}\n\n"
            . "Текст пользователя: \"{$text}\"\n\n"
            . "Верни JSON строго в формате:\n"
            . "{\n"
            . "  \"type\": \"expense\" или \"income\",\n"
            . "  \"amount\": число (без символов валюты),\n"
            . "  \"description\": \"краткое описание на русском\",\n"
            . "  \"category\": \"одна категория из списка выше\",\n"
            . "  \"date\": \"YYYY-MM-DD или null если дата не указана\"\n"
            . "}";

        $response = (new AiService())->complete($system, [['role' => 'user', 'content' => $prompt]], 256);

        // Strip possible markdown code blocks
        $response = preg_replace('/```(?:json)?\s*|\s*```/', '', $response);

        if (preg_match('/\{.*\}/s', $response, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded) && isset($decoded['amount'])) {
                return $decoded;
            }
        }

        return null;
    }

    // ── Inline keyboard callbacks ─────────────────────────────────────────────

    private function handleCallback(array $cbq): void
    {
        $cbqId     = $cbq['id'];
        $chatId    = $cbq['message']['chat']['id'];
        $messageId = $cbq['message']['message_id'];
        $data      = $cbq['data'] ?? '';

        // Always answer callback to remove loading spinner
        $this->answerCallback($cbqId);

        $pending = $this->getPending();

        if (!$pending || (int) $pending['chat_id'] !== $chatId) {
            $this->editMessage($chatId, $messageId, '❌ Действие устарело. Отправьте запись заново.');
            return;
        }

        switch ($data) {
            case 'tx_confirm':
                $this->confirmTransaction($chatId, $messageId, $pending);
                break;

            case 'tx_cancel':
                $this->clearPending();
                $this->editMessage($chatId, $messageId, '❌ Отменено.');
                break;

            case 'tx_edit':
                $this->showEditMenu($chatId, $messageId, $pending);
                break;

            case 'edit_type':
            case 'edit_amount':
            case 'edit_date':
            case 'edit_category':
            case 'edit_desc':
                // 'edit_type' → 'type', 'edit_amount' → 'amount', etc.
                $field                 = substr($data, 5);
                $pending['editing']    = $field;
                $pending['message_id'] = $messageId;
                $this->savePending($pending);
                $this->editMessage(
                    $chatId,
                    $messageId,
                    $this->editPromptText($field),
                    ['inline_keyboard' => [[['text' => '← Отмена', 'callback_data' => 'edit_cancel']]]]
                );
                break;

            case 'edit_cancel':
                $pending['editing'] = null;
                $this->savePending($pending);
                $this->editMessage($chatId, $messageId, $this->formatConfirmText($pending), $this->confirmKeyboard());
                break;
        }
    }

    private function confirmTransaction(int $chatId, int $messageId, array $pending): void
    {
        FinanceEntry::create([
            'amount'      => $pending['amount'],
            'description' => $pending['description'],
            'category_id' => $pending['category_id'],
            'date'        => $pending['date'] ?? today()->toDateString(),
            'source'      => 'telegram',
            'type'        => $pending['type'],
        ]);

        $symbol   = Setting::get('currency_symbol', '$');
        $icon     = $pending['type'] === 'income' ? '💚' : '💸';
        $amount   = number_format((float) $pending['amount'], 0, '.', ' ');
        $dateStr  = $this->formatDateLabel($pending['date'] ?? today()->toDateString());

        $this->clearPending();
        $this->editMessage(
            $chatId,
            $messageId,
            "{$icon} Сохранено!\n\n{$amount} {$symbol} — {$pending['description']}\n📁 {$pending['category']}\n📅 {$dateStr}"
        );
    }

    private function showEditMenu(int $chatId, int $messageId, array $pending): void
    {
        $this->editMessage(
            $chatId,
            $messageId,
            $this->formatConfirmText($pending) . "\n\n✏️ Что изменить?",
            [
                'inline_keyboard' => [
                    [
                        ['text' => '↕️ Тип',      'callback_data' => 'edit_type'],
                        ['text' => '💰 Сумма',     'callback_data' => 'edit_amount'],
                    ],
                    [
                        ['text' => '📅 Дата',      'callback_data' => 'edit_date'],
                        ['text' => '📁 Категория', 'callback_data' => 'edit_category'],
                    ],
                    [
                        ['text' => '📝 Описание',  'callback_data' => 'edit_desc'],
                        ['text' => '← Назад',      'callback_data' => 'edit_cancel'],
                    ],
                ],
            ]
        );
    }

    // ── Edit: user types new value ────────────────────────────────────────────

    private function handleEditResponse(int $chatId, string $text, array $pending): void
    {
        $field     = $pending['editing'];
        $messageId = (int) ($pending['message_id'] ?? 0);

        switch ($field) {
            case 'amount':
                // Accept: 60000 / 60 000 / 60,000 / 60к / 60k / 2млн / 2.5m
                $clean  = preg_replace('/[\s,]/', '', $text);
                $clean  = preg_replace('/млн$/iu', '000000', $clean);
                $clean  = preg_replace('/[мm]$/iu',  '000000', $clean);
                $clean  = preg_replace('/[кk]$/iu',  '000', $clean);
                $amount = (float) $clean;
                if ($amount <= 0) {
                    $this->sendMessage($chatId, '⚠️ Введите корректную сумму (например: 60000, 60к или 2млн)');
                    return;
                }
                $pending['amount'] = $amount;
                break;

            case 'type':
                $lower           = mb_strtolower(trim($text));
                $pending['type'] = (str_contains($lower, 'доход') || str_contains($lower, 'income') || $lower === '+')
                    ? 'income'
                    : 'expense';
                break;

            case 'date':
                $pending['date'] = $this->parseDate($text);
                break;

            case 'category':
                $cat                    = $this->resolveCategory($text);
                $pending['category']    = $cat?->name ?? trim($text);
                $pending['category_id'] = $cat?->id;
                break;

            case 'desc':
                $pending['description'] = trim($text);
                break;
        }

        $pending['editing'] = null;
        $this->savePending($pending);

        if ($messageId) {
            $this->editMessage($chatId, $messageId, $this->formatConfirmText($pending), $this->confirmKeyboard());
        }
    }

    // ── Date helpers ──────────────────────────────────────────────────────────

    /**
     * Parse a user-supplied date string into YYYY-MM-DD.
     * Accepts: сегодня / вчера / позавчера / 23 / 23.02 / 23.02.2026 / 3 марта
     */
    private function parseDate(string $text): string
    {
        $text = mb_strtolower(trim($text));

        if (in_array($text, ['сегодня', 'today', ''])) {
            return today()->toDateString();
        }
        if (in_array($text, ['вчера', 'yesterday'])) {
            return today()->subDay()->toDateString();
        }
        if (in_array($text, ['позавчера', 'два дня назад'])) {
            return today()->subDays(2)->toDateString();
        }

        // DD.MM.YYYY or DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $text, $m)) {
            try { return Carbon::createFromDate((int)$m[3], (int)$m[2], (int)$m[1])->toDateString(); } catch (\Throwable) {}
        }

        // DD.MM or DD/MM (current year)
        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})$/', $text, $m)) {
            try { return Carbon::createFromDate(now()->year, (int)$m[2], (int)$m[1])->toDateString(); } catch (\Throwable) {}
        }

        // Just day number (current month)
        if (preg_match('/^(\d{1,2})$/', $text, $m)) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= 31) {
                try { return Carbon::createFromDate(now()->year, now()->month, $day)->toDateString(); } catch (\Throwable) {}
            }
        }

        // "3 марта", "15 февр", etc.
        $months = [
            'янв' => 1, 'фев' => 2, 'мар' => 3, 'апр' => 4,
            'май' => 5, 'мая' => 5, 'июн' => 6, 'июл' => 7,
            'авг' => 8, 'сен' => 9, 'окт' => 10, 'ноя' => 11, 'дек' => 12,
        ];
        foreach ($months as $abbr => $month) {
            if (preg_match('/(\d{1,2})\s+' . $abbr . '/', $text, $m)) {
                try { return Carbon::createFromDate(now()->year, $month, (int)$m[1])->toDateString(); } catch (\Throwable) {}
            }
        }

        // YYYY-MM-DD (from AI)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            try { return Carbon::parse($text)->toDateString(); } catch (\Throwable) {}
        }

        return today()->toDateString();
    }

    /** Human-readable date label in Russian */
    private function formatDateLabel(string $dateStr): string
    {
        try {
            $date = Carbon::parse($dateStr);
        } catch (\Throwable) {
            return $dateStr;
        }

        if ($date->isToday())     return 'Сегодня';
        if ($date->isYesterday()) return 'Вчера';

        return $date->locale('ru')->isoFormat('D MMM YYYY');
    }

    // ── Formatting ────────────────────────────────────────────────────────────

    private function formatConfirmText(array $pending): string
    {
        $symbol   = Setting::get('currency_symbol', '$');
        $typeStr  = $pending['type'] === 'income' ? '📈 Доход' : '📉 Расход';
        $amount   = number_format((float) $pending['amount'], 0, '.', ' ');
        $dateStr  = $this->formatDateLabel($pending['date'] ?? today()->toDateString());

        return "🤖 Добавить запись?\n\n"
            . "{$typeStr}\n"
            . "💰 {$amount} {$symbol}\n"
            . "📅 {$dateStr}\n"
            . "📁 {$pending['category']}\n"
            . "📝 {$pending['description']}";
    }

    private function confirmKeyboard(): array
    {
        return [
            'inline_keyboard' => [[
                ['text' => '✅ Добавить',  'callback_data' => 'tx_confirm'],
                ['text' => '✏️ Изменить', 'callback_data' => 'tx_edit'],
                ['text' => '❌ Отмена',   'callback_data' => 'tx_cancel'],
            ]],
        ];
    }

    private function editPromptText(string $field): string
    {
        if ($field === 'category') {
            $cats = FinanceCategory::pluck('name')->join(', ');
            return "📁 Введите категорию:\n{$cats}";
        }

        return match ($field) {
            'amount' => "💰 Введите новую сумму:\n(например: 60000, 60к, 2млн)",
            'type'   => "↕️ Введите тип:\nрасход или доход",
            'date'   => "📅 Введите дату:\nсегодня / вчера / 23 / 23.02 / 23.02.2026",
            'desc'   => '📝 Введите новое описание:',
            default  => 'Введите новое значение:',
        };
    }

    // ── Pending state (stored in settings) ───────────────────────────────────

    private function getPending(): ?array
    {
        $raw = Setting::get('telegram_pending_tx');
        if (!$raw) return null;
        return json_decode($raw, true) ?: null;
    }

    private function savePending(array $pending): void
    {
        Setting::set('telegram_pending_tx', json_encode($pending, JSON_UNESCAPED_UNICODE));
    }

    private function clearPending(): void
    {
        Setting::set('telegram_pending_tx', null);
    }

    // ── Existing handlers ─────────────────────────────────────────────────────

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
            'type'        => 'expense',
        ]);

        $symbol = Setting::get('currency_symbol', '$');
        $this->sendMessage($chatId, "✅ Добавлено: {$symbol}{$amount} — {$description}");
    }

    private function handleToday(int $chatId): void
    {
        $entries = FinanceEntry::with('category')->whereDate('date', today())->get();

        if ($entries->isEmpty()) {
            $this->sendMessage($chatId, "📊 Сегодня записей нет.");
            return;
        }

        $symbol   = Setting::get('currency_symbol', '$');
        $expenses = $entries->where('type', 'expense');
        $incomes  = $entries->where('type', 'income');

        $lines = $entries->map(fn($e) =>
            ($e->type === 'income' ? '📈' : '📉') .
            ' ' . number_format((float) $e->amount, 0, '.', ' ') . " {$symbol}" .
            ' — ' . ($e->description ?? '') .
            ' [' . ($e->category?->name ?? 'Без категории') . ']'
        )->join("\n");

        $totalExp = number_format((float) $expenses->sum('amount'), 0, '.', ' ');
        $totalInc = number_format((float) $incomes->sum('amount'), 0, '.', ' ');

        $this->sendMessage($chatId,
            "📊 За сегодня:\n{$lines}\n\n"
            . "📉 Расходы: {$totalExp} {$symbol}\n"
            . "📈 Доходы:  {$totalInc} {$symbol}"
        );
    }

    private function handleHelp(int $chatId): void
    {
        $this->sendMessage($chatId,
            "🤖 Команды:\n\n"
            . "/add [сумма] [описание] — добавить расход\n"
            . "/today — записи за сегодня\n"
            . "/help — список команд\n\n"
            . "💡 Или просто пишите:\n"
            . "«купил еду на 60к сумов»\n"
            . "«получил зарплату 2 млн»\n"
            . "«вчера потратил 15000 на транспорт»"
        );
    }

    private function handleStart(int $chatId): void
    {
        $webAppUrl = $this->getWebAppUrl();
        if (!$webAppUrl) {
            $this->sendMessage($chatId, "⚠️ Не задан URL веб-приложения.");
            return;
        }

        $this->setChatMenuButton($chatId, $webAppUrl);

        $this->sendMessage(
            $chatId,
            "🚀 Добро пожаловать в MYDY!\n\n"
            . "Команды:\n"
            . "/add [сумма] [описание] — добавить расход\n"
            . "/today — записи за сегодня\n"
            . "/help — список команд\n\n"
            . "💡 Или просто пишите:\n«купил еду на 60к сумов»",
            ['inline_keyboard' => [[['text' => 'Открыть Web App', 'web_app' => ['url' => $webAppUrl]]]]]
        );
    }

    // ── Registration ──────────────────────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $data  = $request->validate(['token' => 'required|string']);
        $token = $data['token'];

        $me = Http::get("https://api.telegram.org/bot{$token}/getMe");
        if (!$me->ok() || !$me->json('ok')) {
            return $this->error('Неверный токен бота. Проверьте токен от @BotFather.', 422);
        }

        $baseUrl = config('app.url') ?: $request->getSchemeAndHttpHost();
        if (!str_starts_with($baseUrl, 'https://')) {
            $baseUrl = preg_replace('#^http://#', 'https://', $baseUrl);
        }

        $webhook  = rtrim($baseUrl, '/') . '/api/telegram/webhook';
        $whResult = Http::asJson()->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url'             => $webhook,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        Log::info('Telegram webhook response', ['response' => $whResult->json()]);

        $botName = $me->json('result.username', 'бот');

        if (!$whResult->ok() || !$whResult->json('ok')) {
            $description = $whResult->json('description') ?: 'Неизвестная ошибка';
            return $this->error("Не удалось зарегистрировать webhook: {$description}", 422);
        }

        Setting::set('telegram_bot_token', $token);

        return $this->success(
            ['message' => "Telegram бот @{$botName} подключён"],
            "Telegram бот подключён"
        );
    }

    // ── Telegram API wrappers ─────────────────────────────────────────────────

    private function sendConfirmationMessage(int $chatId, array $pending): ?int
    {
        $token = Setting::get('telegram_bot_token');
        if (!$token) return null;

        $response = Http::asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'      => $chatId,
            'text'         => $this->formatConfirmText($pending),
            'reply_markup' => $this->confirmKeyboard(),
        ]);

        return $response->json('result.message_id');
    }

    private function editMessage(int $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
    {
        $token = Setting::get('telegram_bot_token');
        if (!$token) return;

        $payload = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
        ];
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        Http::asJson()->post("https://api.telegram.org/bot{$token}/editMessageText", $payload);
    }

    private function answerCallback(string $callbackId): void
    {
        $token = Setting::get('telegram_bot_token');
        if (!$token) return;

        Http::asJson()->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
            'callback_query_id' => $callbackId,
        ]);
    }

    private function sendChatAction(int $chatId, string $action): void
    {
        $token = Setting::get('telegram_bot_token');
        if (!$token) return;

        Http::asJson()->post("https://api.telegram.org/bot{$token}/sendChatAction", [
            'chat_id' => $chatId,
            'action'  => $action,
        ]);
    }

    private function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void
    {
        $token = Setting::get('telegram_bot_token');
        if (!$token) return;

        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        Http::asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
    }

    private function setChatMenuButton(int $chatId, string $webAppUrl): void
    {
        $token = Setting::get('telegram_bot_token');
        if (!$token) return;

        Http::asJson()->post("https://api.telegram.org/bot{$token}/setChatMenuButton", [
            'chat_id'     => $chatId,
            'menu_button' => [
                'type'    => 'web_app',
                'text'    => 'Открыть Web App',
                'web_app' => ['url' => $webAppUrl],
            ],
        ]);
    }

    private function getWebAppUrl(): ?string
    {
        $base = config('app.url') ?: env('APP_URL');
        if (!$base) return null;
        return rtrim($base, '/') . '/tma';
    }

    private function resolveCategory(string $name): ?FinanceCategory
    {
        if (!$name = trim($name)) return null;

        $cat = FinanceCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($cat) return $cat;

        return FinanceCategory::whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($name) . '%'])->first();
    }
}
