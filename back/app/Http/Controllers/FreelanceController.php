<?php

namespace App\Http\Controllers;

use App\Models\FreelanceProject;
use App\Models\FreelanceSession;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FreelanceController extends Controller
{
    // ── Projects ──────────────────────────────────────────────────────────────

    public function projects(): JsonResponse
    {
        $weekStart  = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        $projects = FreelanceProject::orderByDesc('created_at')->get()->map(function ($p) use ($weekStart, $monthStart) {
            $weekSeconds = FreelanceSession::where('project_id', $p->id)
                ->whereNotNull('ended_at')
                ->where('started_at', '>=', $weekStart)
                ->sum('duration_seconds');

            $monthSeconds = FreelanceSession::where('project_id', $p->id)
                ->whereNotNull('ended_at')
                ->where('started_at', '>=', $monthStart)
                ->sum('duration_seconds');

            $hasActive = FreelanceSession::where('project_id', $p->id)
                ->whereNull('ended_at')
                ->exists();

            return [
                'id'                       => $p->id,
                'name'                     => $p->name,
                'color'                    => $p->color,
                'deadline'                 => $p->deadline?->toDateString(),
                'total_seconds_this_week'  => (int) $weekSeconds,
                'total_seconds_this_month' => (int) $monthSeconds,
                'has_active_session'       => $hasActive,
                'created_at'               => $p->created_at?->toIso8601String(),
            ];
        });

        return $this->success($projects);
    }

    public function createProject(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'color'    => 'sometimes|string|max:20',
            'deadline' => 'sometimes|nullable|date',
        ]);

        $project = FreelanceProject::create($data);

        return $this->success($this->formatProject($project), 'Project created', 201);
    }

    public function updateProject(Request $request, FreelanceProject $project): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'color'    => 'sometimes|string|max:20',
            'deadline' => 'sometimes|nullable|date',
        ]);

        $project->update($data);

        return $this->success($this->formatProject($project), 'Project updated');
    }

    public function deleteProject(FreelanceProject $project): JsonResponse
    {
        $project->delete();

        return $this->success(null, 'Project deleted');
    }

    // ── Sessions ──────────────────────────────────────────────────────────────

    public function activeSession(): JsonResponse
    {
        $session = FreelanceSession::active()
            ->with('project')
            ->orderByDesc('started_at')
            ->first();

        if (!$session) {
            return $this->success(null);
        }

        return $this->success($this->formatActiveSession($session));
    }

    public function startTimer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'required|integer|exists:freelance_projects,id',
        ]);

        // Check if any session is currently active
        $existing = FreelanceSession::active()->first();
        if ($existing) {
            return $this->error('Another timer is already running.', 409);
        }

        $session = FreelanceSession::create([
            'project_id'           => $data['project_id'],
            'started_at'           => now(),
            'total_paused_seconds' => 0,
        ]);

        $session->load('project');

        AnalyticsService::track('freelance.session.started');
        return $this->success($this->formatActiveSession($session), 'Timer started', 201);
    }

    public function stopTimer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'note' => 'sometimes|nullable|string|max:1000',
        ]);

        $session = FreelanceSession::active()->orderByDesc('started_at')->first();

        if (!$session) {
            return $this->error('No active timer.', 409);
        }

        // If currently paused, add current pause duration before stopping
        $totalPaused = $session->total_paused_seconds ?? 0;
        if ($session->pause_started_at) {
            $totalPaused += (int) $session->pause_started_at->diffInSeconds(now());
        }

        $now         = now();
        $totalElapsed = (int) $session->started_at->diffInSeconds($now);
        $duration    = max(0, $totalElapsed - $totalPaused);

        $session->update([
            'ended_at'             => $now,
            'duration_seconds'     => $duration,
            'total_paused_seconds' => $totalPaused,
            'pause_started_at'     => null,
            'note'                 => $data['note'] ?? $session->note,
        ]);

        $session->load('project');

        AnalyticsService::track('freelance.session.stopped');
        return $this->success($this->formatSession($session), 'Timer stopped');
    }

    public function pauseTimer(): JsonResponse
    {
        $session = FreelanceSession::active()->orderByDesc('started_at')->first();

        if (!$session) {
            return $this->error('No active timer.', 409);
        }

        if ($session->pause_started_at) {
            return $this->error('Timer is already paused.', 409);
        }

        $session->update(['pause_started_at' => now()]);

        $session->load('project');

        return $this->success($this->formatActiveSession($session), 'Timer paused');
    }

    public function resumeTimer(): JsonResponse
    {
        $session = FreelanceSession::active()->orderByDesc('started_at')->first();

        if (!$session) {
            return $this->error('No active timer.', 409);
        }

        if (!$session->pause_started_at) {
            return $this->error('Timer is not paused.', 409);
        }

        $additionalPaused     = (int) $session->pause_started_at->diffInSeconds(now());
        $newTotalPaused       = ($session->total_paused_seconds ?? 0) + $additionalPaused;

        $session->update([
            'total_paused_seconds' => $newTotalPaused,
            'pause_started_at'     => null,
        ]);

        $session->load('project');

        return $this->success($this->formatActiveSession($session), 'Timer resumed');
    }

    public function sessions(Request $request): JsonResponse
    {
        $filter    = $request->query('filter', 'week');
        $projectId = $request->query('project_id');

        $query = FreelanceSession::with('project')->orderByDesc('started_at');

        match ($filter) {
            'week'  => $query->where('started_at', '>=', now()->startOfWeek()),
            'month' => $query->where('started_at', '>=', now()->startOfMonth()),
            default => null,
        };

        if ($projectId) {
            $query->where('project_id', (int) $projectId);
        }

        $sessions = $query->get()->map(fn($s) => $this->formatSession($s));

        return $this->success($sessions);
    }

    public function createSessionManual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'required|integer|exists:freelance_projects,id',
            'started_at' => 'required|date',
            'ended_at'   => 'required|date|after:started_at',
            'note'       => 'sometimes|nullable|string|max:1000',
        ]);

        $start    = Carbon::parse($data['started_at']);
        $end      = Carbon::parse($data['ended_at']);
        $duration = (int) $start->diffInSeconds($end);

        $session = FreelanceSession::create([
            'project_id'           => $data['project_id'],
            'started_at'           => $start,
            'ended_at'             => $end,
            'duration_seconds'     => $duration,
            'total_paused_seconds' => 0,
            'note'                 => $data['note'] ?? null,
        ]);

        $session->load('project');

        return $this->success($this->formatSession($session), 'Session created', 201);
    }

    public function updateSession(Request $request, FreelanceSession $session): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'sometimes|integer|exists:freelance_projects,id',
            'started_at' => 'sometimes|date',
            'ended_at'   => 'sometimes|nullable|date',
            'note'       => 'sometimes|nullable|string|max:1000',
        ]);

        if (isset($data['started_at'])) {
            $data['started_at'] = Carbon::parse($data['started_at']);
        }

        if (array_key_exists('ended_at', $data)) {
            if ($data['ended_at']) {
                $data['ended_at'] = Carbon::parse($data['ended_at']);
                $start            = isset($data['started_at']) ? $data['started_at'] : $session->started_at;
                $data['duration_seconds'] = max(0, (int) $start->diffInSeconds($data['ended_at']));
            } else {
                $data['ended_at']         = null;
                $data['duration_seconds'] = null;
            }
        }

        $session->update($data);
        $session->load('project');

        return $this->success($this->formatSession($session), 'Session updated');
    }

    public function deleteSession(FreelanceSession $session): JsonResponse
    {
        $session->delete();

        return $this->success(null, 'Session deleted');
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        $filter = $request->query('filter', 'week');

        $from = match ($filter) {
            'month' => now()->startOfMonth(),
            default => now()->startOfWeek(),
        };

        $projects = FreelanceProject::all();

        $rows = $projects->map(function ($p) use ($from) {
            $total = FreelanceSession::where('project_id', $p->id)
                ->whereNotNull('ended_at')
                ->where('started_at', '>=', $from)
                ->sum('duration_seconds');

            return [
                'project_id'      => $p->id,
                'project_name'    => $p->name,
                'project_color'   => $p->color,
                'total_seconds'   => (int) $total,
            ];
        })->filter(fn($r) => $r['total_seconds'] > 0)->values();

        $grandTotal = $rows->sum('total_seconds');

        return $this->success([
            'filter'      => $filter,
            'grand_total' => $grandTotal,
            'projects'    => $rows,
        ]);
    }

    // ── CSV Export ────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $projectId = $request->query('project_id');
        $from      = $request->query('from');
        $to        = $request->query('to');

        $query = FreelanceSession::with('project')
            ->whereNotNull('ended_at')
            ->orderBy('started_at');

        if ($projectId) {
            $query->where('project_id', (int) $projectId);
        }

        if ($from) {
            $query->where('started_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where('started_at', '<=', Carbon::parse($to)->endOfDay());
        }

        $sessions = $query->get();

        $csv   = "Date,Project,Start,End,Duration (h:mm),Note\n";

        foreach ($sessions as $s) {
            $date     = $s->started_at->format('Y-m-d');
            $project  = $s->project?->name ?? '';
            $start    = $s->started_at->format('H:i');
            $end      = $s->ended_at->format('H:i');
            $hours    = intdiv($s->duration_seconds ?? 0, 3600);
            $minutes  = intdiv(($s->duration_seconds ?? 0) % 3600, 60);
            $duration = sprintf('%d:%02d', $hours, $minutes);
            $note     = str_replace(['"', "\n", "\r"], [' ', ' ', ''], $s->note ?? '');

            $csv .= sprintf(
                "%s,\"%s\",%s,%s,%s,\"%s\"\n",
                $date,
                addslashes($project),
                $start,
                $end,
                $duration,
                $note,
            );
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="freelance-sessions.csv"',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatProject(FreelanceProject $p): array
    {
        return [
            'id'       => $p->id,
            'name'     => $p->name,
            'color'    => $p->color,
            'deadline' => $p->deadline?->toDateString(),
        ];
    }

    private function formatActiveSession(FreelanceSession $s): array
    {
        return [
            'session_id'      => $s->id,
            'project_id'      => $s->project_id,
            'project_name'    => $s->project?->name,
            'project_color'   => $s->project?->color ?? '#6366f1',
            'started_at'      => $s->started_at?->toIso8601String(),
            'elapsed_seconds' => $s->getElapsedSeconds(),
            'is_paused'       => !is_null($s->pause_started_at),
        ];
    }

    private function formatSession(FreelanceSession $s): array
    {
        $isActive = is_null($s->ended_at);
        $isPaused = !is_null($s->pause_started_at);

        return [
            'id'               => $s->id,
            'project_id'       => $s->project_id,
            'project_name'     => $s->project?->name,
            'project_color'    => $s->project?->color ?? '#6366f1',
            'started_at'       => $s->started_at?->toIso8601String(),
            'ended_at'         => $s->ended_at?->toIso8601String(),
            'duration_seconds' => $s->duration_seconds,
            'is_active'        => $isActive,
            'is_paused'        => $isPaused,
            'elapsed_seconds'  => $isActive ? $s->getElapsedSeconds() : $s->duration_seconds,
            'note'             => $s->note,
        ];
    }
}
