<?php

namespace App\Http\Controllers;

use App\Models\FinanceEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FinanceEntry::with('category')->orderByDesc('date')->orderByDesc('created_at');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }

        return $this->success($query->paginate(50)->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:finance_categories,id',
            'date'        => 'required|date',
            'source'      => 'in:web,telegram',
            'type'        => 'in:expense,income',
        ]);

        $entry = FinanceEntry::create($data);
        return $this->success($entry->load('category')->toArray(), 'Запись добавлена', 201);
    }

    public function update(Request $request, FinanceEntry $entry): JsonResponse
    {
        $data = $request->validate([
            'amount'      => 'sometimes|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:finance_categories,id',
            'date'        => 'sometimes|date',
            'type'        => 'sometimes|in:expense,income',
        ]);

        $entry->update($data);
        return $this->success($entry->load('category')->toArray(), 'Запись обновлена');
    }

    public function destroy(FinanceEntry $entry): JsonResponse
    {
        $entry->delete();
        return $this->success(message: 'Запись удалена');
    }
}
