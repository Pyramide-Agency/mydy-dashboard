<?php

namespace App\Services;

use App\Models\LmsAssignment;
use App\Models\LmsAnnouncement;
use App\Models\LmsCalendarEvent;
use App\Models\LmsCourse;
use App\Models\LmsGrade;
use App\Models\LmsSubmission;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CanvasService
{
    private string $apiKey;
    private string $domain;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = Setting::get('canvas_api_key', '');
        $this->domain  = rtrim(Setting::get('canvas_domain', ''), '/');
        $this->baseUrl = $this->domain . '/api/v1';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->domain);
    }

    // ── HTTP client ───────────────────────────────────────────────────────────
    // Laravel Http::get() doesn't handle array query params (include[], type[])
    // correctly — it sends them as strings. We build the query string manually.

    private function buildUrl(string $path, array $params = [], int $page = 1): string
    {
        $base = $this->baseUrl . $path;
        $parts = [];

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = rawurlencode($key) . '=' . rawurlencode((string) $v);
                }
            } else {
                $parts[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
            }
        }

        $parts[] = 'per_page=100';
        $parts[] = 'page=' . $page;

        return $base . '?' . implode('&', $parts);
    }

    private function get(string $path, array $query = []): array
    {
        $results = [];
        $page    = 1;

        do {
            $url = $this->buildUrl($path, $query, $page);

            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->get($url);

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'Canvas API error: ' . $response->status() . ' ' . $response->body()
                );
            }

            $data = $response->json();
            if (!is_array($data)) {
                break;
            }

            $results = array_merge($results, $data);

            $link    = $response->header('Link');
            $hasNext = $link && str_contains($link, 'rel="next"');
            $page++;
        } while ($hasNext && count($data) > 0);

        return $results;
    }

    // Same but silently returns [] on error (for optional endpoints)
    private function tryGet(string $path, array $query = []): array
    {
        try {
            return $this->get($path, $query);
        } catch (\Throwable $e) {
            Log::warning('CanvasService: skipping ' . $path . ' — ' . $e->getMessage());
            return [];
        }
    }

    // ── Sync all data ─────────────────────────────────────────────────────────

    public function syncAll(): array
    {
        $courses = $this->syncCourses();
        $synced  = [
            'courses'        => count($courses),
            'assignments'    => 0,
            'announcements'  => 0,
            'calendar_events'=> 0,
            'grades'         => 0,
        ];

        foreach ($courses as $course) {
            $synced['assignments']   += $this->syncAssignments($course);
            $synced['announcements'] += $this->syncAnnouncements($course);
            $synced['grades']        += $this->syncGrades($course) ? 1 : 0;
        }

        $synced['calendar_events'] = $this->syncCalendarEvents($courses);

        return $synced;
    }

    // ── Courses ───────────────────────────────────────────────────────────────

    public function syncCourses(): array
    {
        $data = $this->get('/courses', [
            'enrollment_state' => 'active',
            'enrollment_type'  => 'student',
        ]);

        $courses = [];
        foreach ($data as $item) {
            if (!isset($item['id'])) continue;
            // Skip courses that are not in a normal state
            if (in_array($item['workflow_state'] ?? '', ['deleted', 'unpublished'])) continue;

            $course = LmsCourse::updateOrCreate(
                ['canvas_id' => (string) $item['id']],
                [
                    'name'               => $item['name'] ?? 'Unknown',
                    'course_code'        => $item['course_code'] ?? null,
                    'description'        => isset($item['public_description'])
                        ? strip_tags($item['public_description'])
                        : null,
                    'start_at'           => $item['start_at'] ?? null,
                    'end_at'             => $item['end_at'] ?? null,
                    'image_download_url' => $item['image_download_url'] ?? null,
                    'workflow_state'     => $item['workflow_state'] ?? 'available',
                ]
            );
            $courses[] = $course;
        }

        return $courses;
    }

    // ── Assignments ───────────────────────────────────────────────────────────

    public function syncAssignments(LmsCourse $course): int
    {
        $data = $this->tryGet("/courses/{$course->canvas_id}/assignments", [
            'include[]'  => ['submission'],
            'order_by'   => 'due_at',
        ]);

        $count = 0;
        foreach ($data as $item) {
            if (!isset($item['id'])) continue;

            $type = 'assignment';
            if (!empty($item['is_quiz_assignment'])) {
                $type = 'quiz';
            } elseif (isset($item['submission_types']) && in_array('discussion_topic', (array) $item['submission_types'])) {
                $type = 'discussion';
            }

            $assignment = LmsAssignment::updateOrCreate(
                ['canvas_id' => (string) $item['id']],
                [
                    'course_id'        => $course->id,
                    'name'             => $item['name'] ?? 'Unknown',
                    'description'      => isset($item['description'])
                        ? strip_tags($item['description'])
                        : null,
                    'due_at'           => $item['due_at'] ?? null,
                    'lock_at'          => $item['lock_at'] ?? null,
                    'points_possible'  => $item['points_possible'] ?? null,
                    'submission_types' => is_array($item['submission_types'] ?? null)
                        ? implode(',', $item['submission_types'])
                        : null,
                    'assignment_type'  => $type,
                    'workflow_state'   => $item['workflow_state'] ?? 'published',
                    'html_url'         => $item['html_url'] ?? null,
                ]
            );

            if (isset($item['submission']) && is_array($item['submission'])) {
                $sub = $item['submission'];
                LmsSubmission::updateOrCreate(
                    ['assignment_id' => $assignment->id],
                    [
                        'canvas_id'      => isset($sub['id']) ? (string) $sub['id'] : null,
                        'workflow_state' => $sub['workflow_state'] ?? 'unsubmitted',
                        'score'          => $sub['score'] ?? null,
                        'grade'          => is_numeric($sub['grade'] ?? null) ? $sub['grade'] : null,
                        'grade_str'      => !is_numeric($sub['grade'] ?? null) ? ($sub['grade'] ?? null) : null,
                        'submitted_at'   => $sub['submitted_at'] ?? null,
                        'graded_at'      => $sub['graded_at'] ?? null,
                        'late'           => $sub['late'] ?? false,
                        'missing'        => $sub['missing'] ?? false,
                    ]
                );
            }

            $count++;
        }

        return $count;
    }

    // ── Announcements ─────────────────────────────────────────────────────────

    public function syncAnnouncements(LmsCourse $course): int
    {
        $data = $this->tryGet('/announcements', [
            'context_codes[]' => ['course_' . $course->canvas_id],
        ]);

        $count = 0;
        foreach ($data as $item) {
            if (!isset($item['id'])) continue;

            LmsAnnouncement::updateOrCreate(
                ['canvas_id' => (string) $item['id']],
                [
                    'course_id'         => $course->id,
                    'title'             => $item['title'] ?? 'No title',
                    'message'           => isset($item['message'])
                        ? strip_tags($item['message'])
                        : null,
                    'author'            => $item['author']['display_name'] ?? null,
                    'author_avatar_url' => $item['author']['avatar_image_url'] ?? null,
                    'posted_at'         => $item['posted_at'] ?? null,
                    'html_url'          => $item['html_url'] ?? null,
                ]
            );
            $count++;
        }

        return $count;
    }

    // ── Calendar events ───────────────────────────────────────────────────────

    public function syncCalendarEvents(array $courses): int
    {
        if (empty($courses)) return 0;

        $contextCodes = array_map(fn($c) => 'course_' . $c->canvas_id, $courses);
        $courseMap    = collect($courses)->keyBy('canvas_id');
        $count        = 0;

        // Canvas requires one context_code per request or batches of them
        // Send all at once — Canvas supports up to ~10 context codes per request
        $chunks = array_chunk($contextCodes, 10);

        foreach ($chunks as $chunk) {
            $data = $this->tryGet('/calendar_events', [
                'context_codes[]' => $chunk,
                'start_date'      => now()->startOfMonth()->toDateString(),
                'end_date'        => now()->addMonths(3)->endOfMonth()->toDateString(),
                'type'            => 'event',
            ]);

            foreach ($data as $item) {
                if (!isset($item['id'])) continue;

                $canvasCourseId = null;
                if (isset($item['context_code']) && str_starts_with($item['context_code'], 'course_')) {
                    $canvasCourseId = substr($item['context_code'], 7);
                }

                LmsCalendarEvent::updateOrCreate(
                    ['canvas_id' => (string) $item['id']],
                    [
                        'course_id'   => $canvasCourseId ? ($courseMap->get($canvasCourseId)?->id) : null,
                        'title'       => $item['title'] ?? 'Event',
                        'description' => isset($item['description'])
                            ? strip_tags($item['description'])
                            : null,
                        'start_at'    => $item['start_at'] ?? null,
                        'end_at'      => $item['end_at'] ?? null,
                        'location'    => $item['location_name'] ?? null,
                        'event_type'  => $item['type'] ?? 'event',
                        'html_url'    => $item['html_url'] ?? null,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    // ── Grades ────────────────────────────────────────────────────────────────

    public function syncGrades(LmsCourse $course): bool
    {
        // Use /courses/:id/enrollments with grades included
        $data = $this->tryGet("/courses/{$course->canvas_id}/enrollments", [
            'type[]'     => ['StudentEnrollment'],
            'include[]'  => ['observed_users', 'avatar_url', 'group_ids'],
        ]);

        // Fallback: try without extra includes if grades not found
        if (empty($data)) {
            $data = $this->tryGet("/courses/{$course->canvas_id}/enrollments", [
                'type[]' => ['StudentEnrollment'],
            ]);
        }

        $enrollment = $data[0] ?? null;
        $grades     = $enrollment['grades'] ?? null;

        // Canvas returns grades object but without scores unless institution exposes them
        if (!$grades || !isset($grades['current_score'])) {
            return $this->syncGradesDirect($course);
        }

        LmsGrade::updateOrCreate(
            ['course_id' => $course->id],
            [
                'current_score'  => $grades['current_score'],
                'final_score'    => $grades['final_score'] ?? null,
                'current_grade'  => $grades['current_grade'] ?? null,
                'final_grade'    => $grades['final_grade'] ?? null,
                'current_points' => $grades['current_points'] ?? null,
                'final_points'   => $grades['final_points'] ?? null,
            ]
        );

        return true;
    }

    private function syncGradesDirect(LmsCourse $course): bool
    {
        // Alternative: /api/v1/courses/:id?include[]=total_scores
        try {
            $url      = $this->buildUrl("/courses/{$course->canvas_id}", ['include[]' => ['total_scores']], 1);
            $response = Http::withToken($this->apiKey)->timeout(15)->get($url);

            if (!$response->successful()) return false;

            $data = $response->json();
            if (!isset($data['enrollments'])) return false;

            foreach ($data['enrollments'] as $enrollment) {
                if (($enrollment['type'] ?? '') !== 'student') continue;

                LmsGrade::updateOrCreate(
                    ['course_id' => $course->id],
                    [
                        'current_score'  => $enrollment['computed_current_score'] ?? null,
                        'final_score'    => $enrollment['computed_final_score'] ?? null,
                        'current_grade'  => $enrollment['computed_current_grade'] ?? null,
                        'final_grade'    => $enrollment['computed_final_grade'] ?? null,
                        'current_points' => null,
                        'final_points'   => null,
                    ]
                );
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('CanvasService: syncGradesDirect failed for course ' . $course->canvas_id . ' — ' . $e->getMessage());
        }

        return false;
    }
}
