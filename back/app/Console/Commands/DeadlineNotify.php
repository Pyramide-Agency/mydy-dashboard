<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeadlineNotify extends Command
{
    protected $signature   = 'deadlines:notify {--test : Send a test notification ignoring time windows}';
    protected $description = 'Send Telegram notifications for upcoming task deadlines';

    // Hours before deadline to send notifications
    private const THRESHOLDS = [12, 3, 1];

    public function handle(): int
    {
        $enabled = Setting::get('deadline_notifications', '0');
        if ($enabled !== '1') {
            return self::SUCCESS;
        }

        $token  = Setting::get('telegram_bot_token');
        $chatId = Setting::get('telegram_chat_id');

        if (!$token) {
            Log::info('DeadlineNotify: bot token not set, skipping');
            return self::SUCCESS;
        }

        // Try to resolve chat_id from recent updates if not stored yet
        if (!$chatId) {
            $chatId = $this->resolveChatId($token);
            if ($chatId) {
                Setting::set('telegram_chat_id', $chatId);
                Log::info('DeadlineNotify: resolved chat_id from updates', ['chat_id' => $chatId]);
            } else {
                Log::info('DeadlineNotify: chat_id not set and no updates found, skipping');
                return self::SUCCESS;
            }
        }

        $now    = Carbon::now();
        $isTest = $this->option('test');

        if ($isTest) {
            // In test mode: send notification for all upcoming tasks regardless of window
            $tasks = Task::where('archived', false)
                ->whereNotNull('deadline')
                ->where('deadline', '>', $now)
                ->orderBy('deadline')
                ->limit(3)
                ->get();

            if ($tasks->isEmpty()) {
                $this->sendRaw($token, $chatId, '✅ Уведомления о дедлайнах работают! Активных дедлайнов нет.');
                $this->line('Test notification sent (no upcoming deadlines).');
            } else {
                foreach ($tasks as $task) {
                    $diffH = (int) $now->diffInHours(Carbon::parse($task->deadline), false);
                    $this->sendNotification($token, $chatId, $task, $diffH);
                    $this->line("Test notification sent for task #{$task->id}");
                }
            }
            return self::SUCCESS;
        }

        foreach (self::THRESHOLDS as $hours) {
            // Window: tasks whose deadline is within [now + Nh - 5min, now + Nh + 5min]
            $windowStart = $now->copy()->addHours($hours)->subMinutes(5);
            $windowEnd   = $now->copy()->addHours($hours)->addMinutes(5);

            $tasks = Task::where('archived', false)
                ->whereNotNull('deadline')
                ->whereBetween('deadline', [$windowStart, $windowEnd])
                ->get();

            foreach ($tasks as $task) {
                // Skip if already notified at this threshold
                $notifiedKey = "deadline_notified_{$task->id}_{$hours}h";
                if (Setting::get($notifiedKey)) {
                    continue;
                }

                $this->sendNotification($token, $chatId, $task, $hours);
                Setting::set($notifiedKey, '1');

                $this->line("Notified: task #{$task->id} ({$hours}h threshold)");
            }
        }

        // Clean up old notification flags for archived/deleted tasks (run once a day approx.)
        if ((int) $now->format('H') === 3) {
            $this->cleanupFlags();
        }

        return self::SUCCESS;
    }

    private function sendNotification(string $token, string $chatId, Task $task, int $hours): void
    {
        $tz       = Setting::get('user_timezone', 'UTC');
        $deadline = Carbon::parse($task->deadline)->setTimezone($tz);
        $timeStr  = $deadline->format('d.m.Y H:i');

        $hoursLabel = match ($hours) {
            1  => '1 час',
            3  => '3 часа',
            12 => '12 часов',
            default => "{$hours} ч",
        };

        $icon = match ($hours) {
            1  => '🔴',
            3  => '🟠',
            12 => '🟡',
            default => '⏰',
        };

        $text = "{$icon} *Дедлайн через {$hoursLabel}!*\n\n"
            . "📋 {$task->title}\n"
            . "🕐 {$timeStr}";

        if ($task->description) {
            $text .= "\n📝 {$task->description}";
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::error('DeadlineNotify: failed to send message', [
                'task_id' => $task->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function sendRaw(string $token, string $chatId, string $text): void
    {
        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text'    => $text,
            ]);
        } catch (\Throwable $e) {
            Log::error('DeadlineNotify: sendRaw failed', ['error' => $e->getMessage()]);
        }
    }

    private function resolveChatId(string $token): ?string
    {
        try {
            $res = Http::get("https://api.telegram.org/bot{$token}/getUpdates", [
                'limit'   => 10,
                'timeout' => 0,
            ]);
            $updates = $res->json('result') ?? [];
            foreach (array_reverse($updates) as $update) {
                $chatId = $update['message']['chat']['id']
                    ?? $update['callback_query']['message']['chat']['id']
                    ?? null;
                if ($chatId) {
                    return (string) $chatId;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('DeadlineNotify: getUpdates failed', ['error' => $e->getMessage()]);
        }
        return null;
    }

    private function cleanupFlags(): void
    {
        // Remove notification flags for tasks that no longer exist or are archived
        $activeTaskIds = Task::where('archived', false)->pluck('id')->toArray();

        $flagPattern = 'deadline_notified_%';
        $flags = Setting::where('key', 'like', $flagPattern)->get();

        foreach ($flags as $flag) {
            // Extract task id from key like "deadline_notified_42_12h"
            if (preg_match('/deadline_notified_(\d+)_/', $flag->key, $m)) {
                $taskId = (int) $m[1];
                if (!in_array($taskId, $activeTaskIds)) {
                    $flag->delete();
                }
            }
        }
    }
}
