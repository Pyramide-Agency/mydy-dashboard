<?php

namespace App\Console\Commands;

use App\Models\LmsAssignment;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LmsDeadlineNotify extends Command
{
    protected $signature   = 'lms:deadline-notify {--test : Send test notification}';
    protected $description = 'Send Telegram notifications for upcoming Canvas LMS assignment deadlines';

    private const THRESHOLDS = [24, 3, 1]; // hours before deadline

    public function handle(): int
    {
        $enabled = Setting::get('lms_deadline_notifications', '0');
        if ($enabled !== '1' && !$this->option('test')) {
            return self::SUCCESS;
        }

        $token  = Setting::get('telegram_bot_token');
        $chatId = Setting::get('telegram_chat_id');

        if (!$token || !$chatId) {
            Log::info('LmsDeadlineNotify: Telegram not configured, skipping');
            return self::SUCCESS;
        }

        $now    = Carbon::now();
        $isTest = $this->option('test');

        if ($isTest) {
            $assignments = LmsAssignment::with('course')
                ->whereNotNull('due_at')
                ->where('due_at', '>', $now)
                ->where('workflow_state', 'published')
                ->orderBy('due_at')
                ->limit(3)
                ->get();

            if ($assignments->isEmpty()) {
                $this->sendRaw($token, $chatId, '✅ LMS уведомления работают! Ближайших дедлайнов нет.');
            } else {
                foreach ($assignments as $assignment) {
                    $hours = (int) $now->diffInHours(Carbon::parse($assignment->due_at), false);
                    $this->sendNotification($token, $chatId, $assignment, $hours);
                }
            }
            return self::SUCCESS;
        }

        foreach (self::THRESHOLDS as $hours) {
            $windowStart = $now->copy()->addHours($hours)->subMinutes(5);
            $windowEnd   = $now->copy()->addHours($hours)->addMinutes(5);

            $assignments = LmsAssignment::with('course')
                ->whereNotNull('due_at')
                ->whereBetween('due_at', [$windowStart, $windowEnd])
                ->where('workflow_state', 'published')
                ->get();

            foreach ($assignments as $assignment) {
                $notifiedKey = "lms_notified_{$assignment->id}_{$hours}h";
                if (Setting::get($notifiedKey)) {
                    continue;
                }

                $this->sendNotification($token, $chatId, $assignment, $hours);
                Setting::set($notifiedKey, '1');

                $this->line("Notified: assignment #{$assignment->id} ({$hours}h threshold)");
            }
        }

        return self::SUCCESS;
    }

    private function sendNotification(string $token, string $chatId, LmsAssignment $assignment, int $hours): void
    {
        $tz      = Setting::get('user_timezone', 'UTC');
        $dueAt   = Carbon::parse($assignment->due_at)->setTimezone($tz);
        $timeStr = $dueAt->format('d.m.Y H:i');

        $hoursLabel = match ($hours) {
            1  => '1 час',
            3  => '3 часа',
            24 => '24 часа',
            default => "{$hours} ч",
        };

        $icon = match ($hours) {
            1  => '🔴',
            3  => '🟠',
            24 => '🟡',
            default => '⏰',
        };

        $typeIcon = match ($assignment->assignment_type) {
            'quiz'       => '📝',
            'discussion' => '💬',
            default      => '📋',
        };

        $courseName = $assignment->course?->name ?? 'Unknown Course';

        $text = "{$icon} *LMS дедлайн через {$hoursLabel}!*\n\n"
            . "{$typeIcon} {$assignment->name}\n"
            . "📚 {$courseName}\n"
            . "🕐 {$timeStr}";

        if ($assignment->points_possible) {
            $text .= "\n⭐ {$assignment->points_possible} баллов";
        }

        if ($assignment->html_url) {
            $text .= "\n🔗 [Открыть задание]({$assignment->html_url})";
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::error('LmsDeadlineNotify: failed to send', [
                'assignment_id' => $assignment->id,
                'error'         => $e->getMessage(),
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
            Log::error('LmsDeadlineNotify: sendRaw failed', ['error' => $e->getMessage()]);
        }
    }
}
