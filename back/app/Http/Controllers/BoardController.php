<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Column;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    public function index(): JsonResponse
    {
        $boards = Board::withCount(['tasks' => fn($q) => $q->where('archived', false)])->get();
        return $this->success($boards->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $board = Board::create($data);

        // Create default columns for new board
        Column::insert([
            ['board_id' => $board->id, 'name' => 'Создано',  'status_key' => 'created',     'position' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['board_id' => $board->id, 'name' => 'В работе', 'status_key' => 'in_progress',  'position' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['board_id' => $board->id, 'name' => 'Готово',   'status_key' => 'done',          'position' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        return $this->success($board->load('columns')->toArray(), 'Доска создана', 201);
    }

    public function show(Board $board): JsonResponse
    {
        $board->load(['columns.tasks' => fn($q) => $q->orderBy('position')]);
        return $this->success($board->toArray());
    }

    public function update(Request $request, Board $board): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $board->update($data);
        return $this->success($board->toArray(), 'Доска обновлена');
    }

    public function destroy(Board $board): JsonResponse
    {
        if ($board->is_default) {
            return $this->error('Нельзя удалить основную доску', 422);
        }

        $board->delete();
        return $this->success(message: 'Доска удалена');
    }
}
