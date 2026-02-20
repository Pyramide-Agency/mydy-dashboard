<?php

namespace App\Http\Controllers;

use App\Models\FinanceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FinanceCategory::withCount('entries')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'required|string|max:7',
            'icon'  => 'nullable|string|max:100',
        ]);

        return response()->json(FinanceCategory::create($data), 201);
    }

    public function update(Request $request, FinanceCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:7',
            'icon'  => 'nullable|string|max:100',
        ]);

        $category->update($data);
        return response()->json($category);
    }

    public function destroy(FinanceCategory $category): JsonResponse
    {
        $category->delete();
        return response()->json(null, 204);
    }
}
