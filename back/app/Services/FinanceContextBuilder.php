<?php

namespace App\Services;

use App\Models\FinanceEntry;
use App\Models\Setting;

class FinanceContextBuilder
{
    private string $currency;

    public function __construct()
    {
        $this->currency = Setting::get('currency_symbol', '$');
    }

    /**
     * Compact context for feedback() — today detail + yesterday comparison.
     */
    public function buildForFeedback(): array
    {
        $todayEntries = FinanceEntry::with('category')
            ->whereDate('date', today())
            ->get();

        $expenses = $todayEntries->where('type', 'expense');
        $incomes  = $todayEntries->where('type', 'income');

        $yesterdayExpense = (float) FinanceEntry::whereDate('date', today()->subDay())
            ->where('type', 'expense')
            ->sum('amount');

        return [
            'date'              => today()->toDateString(),
            'currency'          => $this->currency,
            'today_expense'     => round($expenses->sum('amount'), 2),
            'today_income'      => round($incomes->sum('amount'), 2),
            'yesterday_expense' => $yesterdayExpense,
            'by_cat'            => $this->groupByCategory($expenses),
            // Raw entries only for today (small number)
            'entries'           => $expenses->map(fn($e) => [
                'a' => (float) $e->amount,
                'd' => $e->description ?? '',
                'c' => $e->category?->name ?? 'Без категории',
            ])->values()->toArray(),
        ];
    }

    /**
     * Smart context for sendMessage() — detects period from user message.
     */
    public function buildForMessage(string $userMessage): array
    {
        $lower = mb_strtolower($userMessage);

        $context = [
            'currency' => $this->currency,
            'today'    => $this->todaySummary(),
            'week'     => $this->weekSummary(),
        ];

        if ($this->detectsMonth($lower)) {
            $context['month'] = $this->monthSummary();
        }

        $anomalies = $this->detectAnomalies();
        if (! empty($anomalies)) {
            $context['alerts'] = $anomalies;
        }

        return $context;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function detectsMonth(string $msg): bool
    {
        foreach (['месяц', 'month', 'за всё', 'за все', 'общ', 'итог'] as $kw) {
            if (str_contains($msg, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function todaySummary(): array
    {
        $entries  = FinanceEntry::with('category')->whereDate('date', today())->get();
        $expenses = $entries->where('type', 'expense');
        $incomes  = $entries->where('type', 'income');

        return [
            'expense' => round($expenses->sum('amount'), 2),
            'income'  => round($incomes->sum('amount'), 2),
            'count'   => $expenses->count(),
            'by_cat'  => $this->groupByCategory($expenses),
            // Include raw entries only for today (handful of records)
            'entries' => $expenses->map(fn($e) => [
                'a' => (float) $e->amount,
                'd' => $e->description ?? '',
                'c' => $e->category?->name ?? 'Без категории',
            ])->values()->toArray(),
        ];
    }

    private function weekSummary(): array
    {
        $entries  = FinanceEntry::with('category')
            ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
            ->get();
        $expenses = $entries->where('type', 'expense');
        $incomes  = $entries->where('type', 'income');
        $days     = max(now()->dayOfWeek, 1); // Mon=1…Sun=7

        return [
            'expense'   => round($expenses->sum('amount'), 2),
            'income'    => round($incomes->sum('amount'), 2),
            'daily_avg' => round($expenses->sum('amount') / $days, 2),
            'by_cat'    => $this->groupByCategory($expenses),
        ];
    }

    private function monthSummary(): array
    {
        $entries  = FinanceEntry::with('category')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->get();
        $expenses = $entries->where('type', 'expense');
        $incomes  = $entries->where('type', 'income');

        return [
            'expense'   => round($expenses->sum('amount'), 2),
            'income'    => round($incomes->sum('amount'), 2),
            'daily_avg' => round($expenses->sum('amount') / now()->day, 2),
            'by_cat'    => $this->groupByCategory($expenses),
        ];
    }

    /**
     * Returns alerts for categories that grew >30% vs last week.
     */
    private function detectAnomalies(): array
    {
        $thisWeek = FinanceEntry::with('category')
            ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('type', 'expense')
            ->get()
            ->groupBy(fn($e) => $e->category?->name ?? 'Без категории')
            ->map(fn($g) => $g->sum('amount'));

        $lastWeek = FinanceEntry::with('category')
            ->whereBetween('date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->where('type', 'expense')
            ->get()
            ->groupBy(fn($e) => $e->category?->name ?? 'Без категории')
            ->map(fn($g) => $g->sum('amount'));

        $alerts = [];
        foreach ($thisWeek as $cat => $amount) {
            $prev = $lastWeek[$cat] ?? 0;
            if ($prev > 0 && ($amount / $prev) > 1.3) {
                $pct      = round(($amount / $prev - 1) * 100);
                $alerts[] = "{$cat} +{$pct}% vs прошлая неделя";
            }
        }

        return $alerts;
    }

    private function groupByCategory($entries): array
    {
        return $entries
            ->groupBy(fn($e) => $e->category?->name ?? 'Без категории')
            ->map(fn($g) => round($g->sum('amount'), 2))
            ->sortDesc()
            ->toArray();
    }
}
