<?php

namespace App\Http\Controllers;

use App\Models\FinanceEntry;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceSummaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        $query  = FinanceEntry::with('category');

        match ($period) {
            'today' => $query->whereDate('date', today()),
            'week'  => $query->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('date', now()->month)->whereYear('date', now()->year),
            default => $query->whereDate('date', today()),
        };

        $entries  = $query->get();
        $expenses = $entries->where('type', 'expense');
        $incomes  = $entries->where('type', 'income');

        $byCategory = $expenses
            ->groupBy(fn($e) => $e->category?->name ?? 'Без категории')
            ->map(fn($g) => [
                'total' => round($g->sum('amount'), 2),
                'count' => $g->count(),
                'color' => $g->first()->category?->color ?? '#6b7280',
            ]);

        $byDay = $entries
            ->groupBy(fn($e) => $e->date->toDateString())
            ->map(fn($g) => [
                'income'  => round($g->where('type', 'income')->sum('amount'), 2),
                'expense' => round($g->where('type', 'expense')->sum('amount'), 2),
            ]);

        // Overall balance: initial_balance + all-time income - all-time expense
        $initialBalance = (float) Setting::get('initial_balance', '0');
        $allIncome      = (float) FinanceEntry::where('type', 'income')->sum('amount');
        $allExpense     = (float) FinanceEntry::where('type', 'expense')->sum('amount');
        $overallBalance = round($initialBalance + $allIncome - $allExpense, 2);

        return $this->success([
            'total'           => round($expenses->sum('amount'), 2),
            'total_expense'   => round($expenses->sum('amount'), 2),
            'total_income'    => round($incomes->sum('amount'), 2),
            'net'             => round($incomes->sum('amount') - $expenses->sum('amount'), 2),
            'count'           => $entries->count(),
            'count_expense'   => $expenses->count(),
            'count_income'    => $incomes->count(),
            'period'          => $period,
            'by_category'     => $byCategory,
            'by_day'          => $byDay,
            'overall_balance' => $overallBalance,
            'initial_balance' => $initialBalance,
        ]);
    }
}
