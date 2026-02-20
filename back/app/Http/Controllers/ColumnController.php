<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Column;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColumnController extends Controller
{
    public function index(Board $board): JsonResponse
    {
        return response()->json($board->columns()->with(['tasks' => fn($q) => $q->orderBy('position')])->get());
    }

    public function store(Board $board, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'status_key' => 'required|string',
        ]);

        $position = $board->columns()->max('position') + 1;
        $column = $board->columns()->create([...$data, 'position' => $position]);

        return response()->json($column, 201);
    }

    public function update(Request $request, Column $column): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $column->update($data);
        return response()->json($column);
    }

    public function destroy(Column $column): JsonResponse
    {
        $column->delete();
        return response()->json(null, 204);
    }
}
