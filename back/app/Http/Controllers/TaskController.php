<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Column;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function archived(): JsonResponse
    {
        $tasks = Task::with('column')
            ->where('archived', true)
            ->orderByDesc('archived_at')
            ->get()
            ->groupBy(fn($t) => $t->archived_at->toDateString())
            ->map(fn($group, $date) => [
                'date'  => $date,
                'tasks' => $group->values(),
            ])
            ->values();

        return response()->json($tasks);
    }

    public function archiveDone(Request $request): JsonResponse
    {
        $boardId = $request->input('board_id');

        $query = Task::whereHas('column', fn($q) => $q->where('status_key', 'done'))
            ->where('archived', false);

        if ($boardId) {
            $query->where('board_id', $boardId);
        }

        $count = $query->count();
        $query->update(['archived' => true, 'archived_at' => now()]);

        return response()->json(['archived_count' => $count]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'board_id'    => 'required|exists:boards,id',
            'column_id'   => 'required|exists:columns,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'in:low,medium,high',
        ]);

        $position = Task::where('column_id', $data['column_id'])
            ->where('archived', false)
            ->max('position') + 1;

        $task = Task::create([...$data, 'position' => $position]);

        return response()->json($task->load('column'), 201);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'in:low,medium,high',
        ]);

        $task->update($data);
        return response()->json($task);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();
        return response()->json(null, 204);
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'column_id' => 'required|exists:columns,id',
            'position'  => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($task, $data) {
            $newColumnId  = $data['column_id'];
            $newPosition  = $data['position'];

            // Fill gap in source column
            Task::where('column_id', $task->column_id)
                ->where('position', '>', $task->position)
                ->where('archived', false)
                ->decrement('position');

            // Make room in destination column
            Task::where('column_id', $newColumnId)
                ->where('position', '>=', $newPosition)
                ->where('archived', false)
                ->increment('position');

            $task->update(['column_id' => $newColumnId, 'position' => $newPosition]);
        });

        return response()->json($task->fresh()->load('column'));
    }
}
