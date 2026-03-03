<?php

namespace App\Http\Controllers;

use App\Models\LmsAnnouncement;
use App\Models\LmsAssignment;
use App\Models\LmsCalendarEvent;
use App\Models\LmsCourse;
use App\Models\LmsGrade;
use App\Models\Setting;
use App\Services\CanvasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsController extends Controller
{
    // ── Sync with Canvas ──────────────────────────────────────────────────────

    public function sync(): JsonResponse
    {
        $service = new CanvasService();

        if (!$service->isConfigured()) {
            return $this->error('Canvas API key or domain not configured', 422);
        }

        try {
            $result = $service->syncAll();
            Setting::set('lms_last_sync', now()->toIso8601String());
            return $this->success($result, 'Синхронизация завершена');
        } catch (\Throwable $e) {
            return $this->error('Canvas API error: ' . $e->getMessage(), 500);
        }
    }

    // ── Courses ───────────────────────────────────────────────────────────────

    public function courses(): JsonResponse
    {
        $courses = LmsCourse::with('grade')
            ->where('workflow_state', '!=', 'deleted')
            ->orderBy('name')
            ->get()
            ->map(fn($c) => $this->formatCourse($c));

        return $this->success($courses);
    }

    public function course(LmsCourse $course): JsonResponse
    {
        $course->load(['assignments.submission', 'announcements', 'grade']);

        return $this->success([
            'course'       => $this->formatCourse($course),
            'assignments'  => $course->assignments->map(fn($a) => $this->formatAssignment($a)),
            'announcements'=> $course->announcements->sortByDesc('posted_at')->values()->map(fn($a) => $this->formatAnnouncement($a)),
        ]);
    }

    // ── Assignments ───────────────────────────────────────────────────────────

    public function assignments(Request $request): JsonResponse
    {
        $filter   = $request->query('filter', 'upcoming'); // upcoming, past, all
        $courseId = $request->query('course_id');

        $query = LmsAssignment::with(['course', 'submission'])
            ->where('workflow_state', 'published');

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        match ($filter) {
            'upcoming' => $query->where(fn($q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now())),
            'past'     => $query->whereNotNull('due_at')->where('due_at', '<', now()),
            default    => null,
        };

        $assignments = $query->orderBy('due_at')->get()
            ->map(fn($a) => $this->formatAssignment($a));

        return $this->success($assignments);
    }

    // ── Deadlines ─────────────────────────────────────────────────────────────

    public function deadlines(Request $request): JsonResponse
    {
        $period = $request->query('period', 'week'); // tomorrow, week, month

        $query = LmsAssignment::with(['course', 'submission'])
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->where('workflow_state', 'published');

        match ($period) {
            'tomorrow' => $query->where('due_at', '<=', now()->addDay()->endOfDay()),
            'week'     => $query->where('due_at', '<=', now()->endOfWeek()),
            'month'    => $query->where('due_at', '<=', now()->endOfMonth()),
            default    => null,
        };

        $deadlines = $query->orderBy('due_at')->get()
            ->map(fn($a) => $this->formatAssignment($a));

        return $this->success($deadlines);
    }

    // ── Calendar ──────────────────────────────────────────────────────────────

    public function calendar(Request $request): JsonResponse
    {
        $month = $request->query('month', now()->month);
        $year  = $request->query('year', now()->year);

        $start = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        // Calendar events
        $events = LmsCalendarEvent::with('course')
            ->where('start_at', '>=', $start)
            ->where('start_at', '<=', $end)
            ->orderBy('start_at')
            ->get()
            ->map(fn($e) => [
                'id'         => $e->id,
                'canvas_id'  => $e->canvas_id,
                'title'      => $e->title,
                'description'=> $e->description,
                'start_at'   => $e->start_at?->toIso8601String(),
                'end_at'     => $e->end_at?->toIso8601String(),
                'location'   => $e->location,
                'event_type' => $e->event_type,
                'html_url'   => $e->html_url,
                'course'     => $e->course ? ['id' => $e->course->id, 'name' => $e->course->name, 'color' => $e->course->color] : null,
            ]);

        // Assignments with deadlines in this month
        $assignments = LmsAssignment::with(['course', 'submission'])
            ->whereNotNull('due_at')
            ->where('due_at', '>=', $start)
            ->where('due_at', '<=', $end)
            ->where('workflow_state', 'published')
            ->orderBy('due_at')
            ->get()
            ->map(fn($a) => array_merge($this->formatAssignment($a), ['event_type' => 'assignment_deadline']));

        return $this->success([
            'events'      => $events,
            'assignments' => $assignments,
            'month'       => (int)$month,
            'year'        => (int)$year,
        ]);
    }

    // ── Course timeline (lessons/events for a course) ─────────────────────────

    public function courseTimeline(LmsCourse $course, Request $request): JsonResponse
    {
        $events = LmsCalendarEvent::where('course_id', $course->id)
            ->orderBy('start_at')
            ->get()
            ->map(fn($e) => [
                'id'          => $e->id,
                'canvas_id'   => $e->canvas_id,
                'title'       => $e->title,
                'description' => $e->description,
                'start_at'    => $e->start_at?->toIso8601String(),
                'end_at'      => $e->end_at?->toIso8601String(),
                'location'    => $e->location,
                'event_type'  => $e->event_type,
                'html_url'    => $e->html_url,
            ]);

        $assignments = LmsAssignment::with('submission')
            ->where('course_id', $course->id)
            ->whereNotNull('due_at')
            ->where('workflow_state', 'published')
            ->orderBy('due_at')
            ->get()
            ->map(fn($a) => $this->formatAssignment($a));

        return $this->success([
            'course'      => $this->formatCourse($course),
            'events'      => $events,
            'assignments' => $assignments,
        ]);
    }

    // ── Grades ────────────────────────────────────────────────────────────────

    public function grades(): JsonResponse
    {
        $courses = LmsCourse::with('grade')
            ->where('workflow_state', '!=', 'deleted')
            ->orderBy('name')
            ->get();

        $data = $courses->map(fn($c) => [
            'course'        => ['id' => $c->id, 'name' => $c->name, 'course_code' => $c->course_code, 'color' => $c->color],
            'current_score' => $c->grade?->current_score,
            'final_score'   => $c->grade?->final_score,
            'current_grade' => $c->grade?->current_grade,
            'final_grade'   => $c->grade?->final_grade,
        ]);

        // Compute average GPA-like score
        $scores = $courses->filter(fn($c) => $c->grade?->current_score !== null)
            ->map(fn($c) => (float) $c->grade->current_score);

        $averageScore = $scores->isNotEmpty() ? round($scores->avg(), 1) : null;

        return $this->success([
            'grades'        => $data,
            'average_score' => $averageScore,
        ]);
    }

    // ── Announcements ─────────────────────────────────────────────────────────

    public function announcements(Request $request): JsonResponse
    {
        $courseId = $request->query('course_id');

        $query = LmsAnnouncement::with('course')->orderByDesc('posted_at');

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        $announcements = $query->limit(50)->get()
            ->map(fn($a) => $this->formatAnnouncement($a));

        return $this->success($announcements);
    }

    public function markAnnouncementRead(LmsAnnouncement $announcement): JsonResponse
    {
        $announcement->update(['read' => true]);
        return $this->success(null, 'Отмечено как прочитанное');
    }

    // ── Update course color ───────────────────────────────────────────────────

    public function updateCourse(Request $request, LmsCourse $course): JsonResponse
    {
        $data = $request->validate([
            'color' => 'sometimes|string|max:20',
        ]);

        $course->update($data);

        return $this->success($this->formatCourse($course), 'Обновлено');
    }

    // ── Sync status ───────────────────────────────────────────────────────────

    public function status(): JsonResponse
    {
        $configured = (bool) Setting::get('canvas_api_key') && (bool) Setting::get('canvas_domain');

        return $this->success([
            'configured'  => $configured,
            'enabled'     => Setting::get('lms_enabled') === '1',
            'last_sync'   => Setting::get('lms_last_sync'),
            'domain'      => Setting::get('canvas_domain'),
            'api_key_set' => (bool) Setting::get('canvas_api_key'),
        ]);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatCourse(LmsCourse $c): array
    {
        return [
            'id'          => $c->id,
            'canvas_id'   => $c->canvas_id,
            'name'        => $c->name,
            'course_code' => $c->course_code,
            'description' => $c->description,
            'instructor'  => $c->instructor,
            'start_at'    => $c->start_at?->toIso8601String(),
            'end_at'      => $c->end_at?->toIso8601String(),
            'image_url'   => $c->image_download_url,
            'color'       => $c->color ?? $this->defaultColor($c->canvas_id),
            'grade'       => $c->grade ? [
                'current_score' => $c->grade->current_score,
                'current_grade' => $c->grade->current_grade,
            ] : null,
        ];
    }

    private function formatAssignment(LmsAssignment $a): array
    {
        return [
            'id'              => $a->id,
            'canvas_id'       => $a->canvas_id,
            'name'            => $a->name,
            'description'     => $a->description,
            'due_at'          => $a->due_at?->toIso8601String(),
            'points_possible' => $a->points_possible,
            'assignment_type' => $a->assignment_type,
            'html_url'        => $a->html_url,
            'course'          => $a->course ? ['id' => $a->course->id, 'name' => $a->course->name, 'color' => $a->course->color ?? $this->defaultColor($a->course->canvas_id)] : null,
            'submission'      => $a->submission ? [
                'state'        => $a->submission->workflow_state,
                'score'        => $a->submission->score,
                'grade'        => $a->submission->grade_str ?? $a->submission->grade,
                'submitted_at' => $a->submission->submitted_at?->toIso8601String(),
                'late'         => $a->submission->late,
                'missing'      => $a->submission->missing,
            ] : null,
        ];
    }

    private function formatAnnouncement(LmsAnnouncement $a): array
    {
        return [
            'id'         => $a->id,
            'canvas_id'  => $a->canvas_id,
            'title'      => $a->title,
            'message'    => $a->message,
            'author'     => $a->author,
            'avatar_url' => $a->author_avatar_url,
            'posted_at'  => $a->posted_at?->toIso8601String(),
            'html_url'   => $a->html_url,
            'read'       => $a->read,
            'course'     => $a->course ? ['id' => $a->course->id, 'name' => $a->course->name, 'color' => $a->course->color ?? $this->defaultColor($a->course->canvas_id)] : null,
        ];
    }

    private function defaultColor(string $canvasId): string
    {
        $colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#14b8a6'];
        return $colors[crc32($canvasId) % count($colors)];
    }
}
