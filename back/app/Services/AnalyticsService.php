<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyticsService
{
    /**
     * Returns a stable instance ID derived from APP_URL + APP_KEY.
     * Stored in settings so it survives config changes without changing identity.
     */
    public static function instanceId(): string
    {
        static $cached = null;

        if ($cached) {
            return $cached;
        }

        try {
            $cached = \App\Models\Setting::get('analytics_instance_id');

            if (!$cached) {
                $seed   = config('app.url', '') . config('app.key', '');
                $cached = 'vektron-' . substr(hash('sha256', $seed), 0, 12);
                \App\Models\Setting::set('analytics_instance_id', $cached);
            }
        } catch (\Throwable) {
            // DB not ready (e.g. during migrations) — generate on-the-fly without storing
            $seed   = config('app.url', '') . config('app.key', '');
            $cached = 'vektron-' . substr(hash('sha256', $seed), 0, 12);
        }

        return $cached;
    }

    public static function track(string $event, array $payload = []): void
    {
        $url    = config('services.analytics.url');
        $secret = config('services.analytics.secret');

        if (!$url || !$secret) {
            return;
        }

        try {
            Http::withToken($secret)
                ->timeout(3)
                ->post($url . '/api/ingest', [
                    'event'       => $event,
                    'instance_id' => self::instanceId(),
                    'instance_url' => config('app.url'),
                    'payload'     => $payload,
                    'occurred_at' => now()->toIso8601String(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('[analytics] Failed to track event: ' . $e->getMessage(), [
                'event' => $event,
            ]);
        }
    }
}
