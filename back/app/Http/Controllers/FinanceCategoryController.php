<?php

namespace App\Http\Controllers;

use App\Models\FinanceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(FinanceCategory::withCount('entries')->get()->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'required|string|max:7',
            'icon'  => 'nullable|string|max:100',
        ]);

        return $this->success(FinanceCategory::create($data)->toArray(), 'Категория создана', 201);
    }

    public function update(Request $request, FinanceCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:7',
            'icon'  => 'nullable|string|max:100',
        ]);

        $category->update($data);
        return $this->success($category->toArray(), 'Категория обновлена');
    }

    public function destroy(FinanceCategory $category): JsonResponse
    {
        $category->delete();
        return $this->success(message: 'Категория удалена');
    }
}
