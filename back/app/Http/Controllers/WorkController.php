<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkController extends Controller
{
    // ── Public webhook (key-based auth) ───────────────────────────────────────

    public function webhook(Request $request): JsonResponse
    {
        $key = $request->query('key');
        if (!$key || $key !== Setting::get('work_webhook_key')) {
            return $this->error('Unauthorized', 401);
        }

        // ── Auto-registration: first ever request from this device ───────────
        $registered = Setting::get('work_shortcut_registered_at');
        if (!$registered) {
            Setting::set('work_shortcut_registered_at', now()->toIso8601String());
            return $this->success([
                'action'      => 'registered',
                'registered'  => true,
            ], 'iOS Shortcut registered! Tap again to check in.');
        }

        $action = $request->input('action', 'toggle');
        if (!in_array($action, ['checkin', 'checkout', 'toggle'])) {
            return $this->error('Invalid action. Use: checkin, checkout, toggle.', 422);
        }

        $open = WorkSession::currentOpen();

        // Resolve toggle
        if ($action === 'toggle') {
            $action = $open ? 'checkout' : 'checkin';
        }

        if ($action === 'checkin') {
            if ($open) {
                return $this->error('Already checked in.', 409);
            }
            $session = WorkSession::create(['checked_in_at' => now()]);
            return $this->success(['action' => 'checked_in', 'session_id' => $session->id], 'Checked in');
        }

        // checkout
        if (!$open) {
            return $this->error('No open session to check out from.', 409);
        }
        $open->checkout();
        return $this->success([
            'action'           => 'checked_out',
            'session_id'       => $open->id,
            'duration_minutes' => $open->duration_minutes,
        ], 'Checked out');
    }

    // ── Status ────────────────────────────────────────────────────────────────

    public function status(): JsonResponse
    {
        $open               = WorkSession::currentOpen();
        $webhookEnabled     = Setting::get('work_webhook_enabled') === '1';
        $webhookKey         = Setting::get('work_webhook_key');
        $shortcutRegistered = Setting::get('work_shortcut_registered_at');

        $baseUrl    = config('app.url') ?: request()->getSchemeAndHttpHost();
        $webhookUrl = $webhookKey ? rtrim($baseUrl, '/') . '/api/work/webhook?key=' . $webhookKey : null;

        $common = [
            'webhook_enabled'        => $webhookEnabled,
            'webhook_url'            => $webhookUrl,
            'shortcut_registered'    => (bool) $shortcutRegistered,
            'shortcut_registered_at' => $shortcutRegistered,
        ];

        if (!$open) {
            return $this->success(array_merge($common, [
                'is_checked_in'   => false,
                'session'         => null,
                'elapsed_minutes' => 0,
            ]));
        }

        $elapsed = (int) $open->checked_in_at->diffInMinutes(now());

        return $this->success(array_merge($common, [
            'is_checked_in'   => true,
            'session'         => $this->formatSession($open),
            'elapsed_minutes' => $elapsed,
        ]));
    }

    // ── Session list ──────────────────────────────────────────────────────────

    public function sessions(Request $request): JsonResponse
    {
        $filter = $request->query('filter', 'week');

        $query = WorkSession::orderByDesc('checked_in_at');

        match ($filter) {
            'week'  => $query->where('checked_in_at', '>=', now()->startOfWeek()),
            'month' => $query->where('checked_in_at', '>=', now()->startOfMonth()),
            default => null,
        };

        $sessions = $query->get()->map(fn($s) => $this->formatSession($s));

        return $this->success($sessions);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $weekSessions  = WorkSession::whereNotNull('checked_out_at')
            ->where('checked_in_at', '>=', now()->startOfWeek())
            ->get();

        $monthSessions = WorkSession::whereNotNull('checked_out_at')
            ->where('checked_in_at', '>=', now()->startOfMonth())
            ->get();

        $allSessions = WorkSession::whereNotNull('checked_out_at')->get();

        $avgMinutes = $allSessions->isNotEmpty()
            ? (int) $allSessions->avg('duration_minutes')
            : 0;

        return $this->success([
            'week_minutes'    => (int) $weekSessions->sum('duration_minutes'),
            'month_minutes'   => (int) $monthSessions->sum('duration_minutes'),
            'avg_minutes'     => $avgMinutes,
            'total_shifts'    => $allSessions->count(),
        ]);
    }

    // ── Manual check-in ───────────────────────────────────────────────────────

    public function checkin(Request $request): JsonResponse
    {
        if (WorkSession::currentOpen()) {
            return $this->error('Already checked in.', 409);
        }

        $session = WorkSession::create(['checked_in_at' => now()]);

        return $this->success($this->formatSession($session), 'Checked in');
    }

    // ── Manual check-out ──────────────────────────────────────────────────────

    public function checkout(Request $request): JsonResponse
    {
        $open = WorkSession::currentOpen();

        if (!$open) {
            return $this->error('No open session.', 409);
        }

        $open->checkout();

        return $this->success($this->formatSession($open), 'Checked out');
    }

    // ── Update session ────────────────────────────────────────────────────────

    public function update(Request $request, WorkSession $session): JsonResponse
    {
        $data = $request->validate([
            'checked_in_at'  => 'sometimes|date',
            'checked_out_at' => 'sometimes|nullable|date',
            'note'           => 'sometimes|nullable|string|max:500',
        ]);

        if (isset($data['checked_in_at'])) {
            $session->checked_in_at = Carbon::parse($data['checked_in_at']);
        }

        if (array_key_exists('checked_out_at', $data)) {
            if ($data['checked_out_at']) {
                $session->checked_out_at   = Carbon::parse($data['checked_out_at']);
                $session->duration_minutes = (int) $session->checked_in_at->diffInMinutes($session->checked_out_at);
            } else {
                $session->checked_out_at   = null;
                $session->duration_minutes = null;
            }
        }

        if (array_key_exists('note', $data)) {
            $session->note = $data['note'];
        }

        $session->save();

        return $this->success($this->formatSession($session), 'Session updated');
    }

    // ── Delete session ────────────────────────────────────────────────────────

    public function destroy(WorkSession $session): JsonResponse
    {
        $session->delete();
        return $this->success(null, 'Session deleted');
    }

    // ── Enable / disable webhook (toggle from settings UI) ───────────────────

    public function setEnabled(Request $request): JsonResponse
    {
        $enabled = (bool) $request->input('enabled');

        Setting::set('work_webhook_enabled', $enabled ? '1' : '0');

        if ($enabled) {
            $key = Setting::get('work_webhook_key');
            if (!$key) {
                $key = (string) Str::uuid();
                Setting::set('work_webhook_key', $key);
            }
            $baseUrl = config('app.url') ?: $request->getSchemeAndHttpHost();
            $url     = rtrim($baseUrl, '/') . '/api/work/webhook?key=' . $key;

            return $this->success(['enabled' => true, 'url' => $url]);
        }

        Setting::set('work_shortcut_registered_at', null);

        return $this->success(['enabled' => false]);
    }

    // ── Webhook info ──────────────────────────────────────────────────────────

    public function webhookInfo(Request $request): JsonResponse
    {
        $key = Setting::get('work_webhook_key');

        if (!$key) {
            $key = (string) Str::uuid();
            Setting::set('work_webhook_key', $key);
        }

        $baseUrl = config('app.url') ?: $request->getSchemeAndHttpHost();
        $url     = rtrim($baseUrl, '/') . '/api/work/webhook?key=' . $key;

        return $this->success(['key' => $key, 'url' => $url]);
    }

    // ── Revoke webhook (delete key + registration) ────────────────────────────

    public function revokeWebhook(): JsonResponse
    {
        Setting::set('work_webhook_key', null);
        Setting::set('work_shortcut_registered_at', null);

        return $this->success(null, 'Webhook revoked');
    }

    // ── Regenerate webhook key ────────────────────────────────────────────────

    public function regenerateKey(Request $request): JsonResponse
    {
        $key = (string) Str::uuid();
        Setting::set('work_webhook_key', $key);
        Setting::set('work_shortcut_registered_at', null);

        $baseUrl = config('app.url') ?: $request->getSchemeAndHttpHost();
        $url     = rtrim($baseUrl, '/') . '/api/work/webhook?key=' . $key;

        return $this->success(['key' => $key, 'url' => $url], 'Webhook key regenerated');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function formatSession(WorkSession $s): array
    {
        return [
            'id'               => $s->id,
            'checked_in_at'    => $s->checked_in_at?->toIso8601String(),
            'checked_out_at'   => $s->checked_out_at?->toIso8601String(),
            'duration_minutes' => $s->duration_minutes,
            'note'             => $s->note,
            'created_at'       => $s->created_at?->toIso8601String(),
        ];
    }
}
