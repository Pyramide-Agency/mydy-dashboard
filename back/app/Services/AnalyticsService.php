<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyticsService
{
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
                    'payload'     => $payload,
                    'occurred_at' => now()->toIso8601String(),
                ]);
        } catch (\Throwable $e) {
            // Fire-and-forget: never break the main request
            Log::warning('[analytics] Failed to track event: ' . $e->getMessage(), [
                'event' => $event,
            ]);
        }
    }
}
