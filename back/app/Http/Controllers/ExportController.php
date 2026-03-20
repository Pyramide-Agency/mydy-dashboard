<?php

namespace App\Http\Controllers;

use App\Models\AiMemory;
use App\Models\Board;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\FreelanceProject;
use App\Models\FreelanceSession;
use App\Models\LmsAssignment;
use App\Models\LmsGrade;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    public function finance(Request $request): Response
    {
        $format = $request->query('format', 'csv');

        $entries    = FinanceEntry::with('category')->orderBy('date')->orderBy('created_at')->get();
        $categories = FinanceCategory::orderBy('name')->get();

        if ($format === 'json') {
            $data = [
                'exported_at' => now()->toIso8601String(),
                'categories'  => $categories->map(fn ($c) => [
                    'id'    => $c->id,
                    'name'  => $c->name,
                    'color' => $c->color,
                ]),
                'entries' => $entries->map(fn ($e) => [
                    'id'          => $e->id,
                    'date'        => $e->date,
                    'amount'      => $e->amount,
                    'type'        => $e->amount >= 0 ? 'income' : 'expense',
                    'description' => $e->description,
                    'category'    => $e->category?->name,
                    'source'      => $e->source,
                    'created_at'  => $e->created_at?->toIso8601String(),
                ]),
            ];

            return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="finance-export.json"',
            ]);
        }

        $csv = "Date,Amount,Type,Description,Category,Source\n";
        foreach ($entries as $e) {
            $type     = $e->amount >= 0 ? 'income' : 'expense';
            $desc     = $this->escapeCsv($e->description ?? '');
            $category = $this->escapeCsv($e->category?->name ?? '');
            $csv .= sprintf("%s,%.2f,%s,%s,%s,%s\n",
                $e->date,
                $e->amount,
                $type,
                $desc,
                $category,
                $e->source ?? '',
            );
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="finance-export.csv"',
        ]);
    }

    public function tasks(Request $request): Response
    {
        $format = $request->query('format', 'csv');

        $boards = Board::with(['columns.tasks'])->get();

        if ($format === 'json') {
            $data = [
                'exported_at' => now()->toIso8601String(),
                'boards'      => $boards->map(fn ($b) => [
                    'id'          => $b->id,
                    'name'        => $b->name,
                    'description' => $b->description,
                    'columns'     => $b->columns->sortBy('position')->values()->map(fn ($col) => [
                        'id'    => $col->id,
                        'name'  => $col->name,
                        'tasks' => $col->tasks->sortBy('position')->values()->map(fn ($t) => [
                            'id'          => $t->id,
                            'title'       => $t->title,
                            'description' => $t->description,
                            'priority'    => $t->priority,
                            'deadline'    => $t->deadline,
                            'archived'    => (bool) $t->archived,
                            'created_at'  => $t->created_at?->toIso8601String(),
                        ]),
                    ]),
                ]),
            ];

            return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="tasks-export.json"',
            ]);
        }

        $csv = "Board,Column,Title,Description,Priority,Deadline,Archived,Created\n";
        foreach ($boards as $board) {
            foreach ($board->columns->sortBy('position') as $col) {
                foreach ($col->tasks->sortBy('position') as $t) {
                    $csv .= sprintf("%s,%s,%s,%s,%s,%s,%s,%s\n",
                        $this->escapeCsv($board->name),
                        $this->escapeCsv($col->name),
                        $this->escapeCsv($t->title),
                        $this->escapeCsv($t->description ?? ''),
                        $t->priority ?? '',
                        $t->deadline ?? '',
                        $t->archived ? 'yes' : 'no',
                        $t->created_at?->format('Y-m-d') ?? '',
                    );
                }
            }
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="tasks-export.csv"',
        ]);
    }

    public function work(Request $request): Response
    {
        $format = $request->query('format', 'csv');

        $sessions = WorkSession::whereNotNull('checked_out_at')->orderBy('checked_in_at')->get();

        if ($format === 'json') {
            $data = [
                'exported_at' => now()->toIso8601String(),
                'sessions'    => $sessions->map(fn ($s) => [
                    'id'              => $s->id,
                    'date'            => $s->checked_in_at->format('Y-m-d'),
                    'checked_in_at'   => $s->checked_in_at->toIso8601String(),
                    'checked_out_at'  => $s->checked_out_at->toIso8601String(),
                    'duration_min'    => $s->duration_minutes,
                    'note'            => $s->note,
                ]),
            ];

            return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="work-sessions-export.json"',
            ]);
        }

        $csv = "Date,Check-in,Check-out,Duration (h:mm)\n";
        foreach ($sessions as $s) {
            $total   = ($s->duration_minutes ?? 0) * 60;
            $hours   = intdiv($total, 3600);
            $minutes = intdiv($total % 3600, 60);
            $csv .= sprintf("%s,%s,%s,%d:%02d\n",
                $s->checked_in_at->format('Y-m-d'),
                $s->checked_in_at->format('H:i'),
                $s->checked_out_at->format('H:i'),
                $hours,
                $minutes,
            );
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="work-sessions-export.csv"',
        ]);
    }

    public function freelance(Request $request): Response
    {
        $format = $request->query('format', 'csv');

        $projects = FreelanceProject::orderBy('name')->get();
        $sessions = FreelanceSession::with('project')
            ->whereNotNull('ended_at')
            ->orderBy('started_at')
            ->get();

        if ($format === 'json') {
            $data = [
                'exported_at' => now()->toIso8601String(),
                'projects'    => $projects->map(fn ($p) => [
                    'id'       => $p->id,
                    'name'     => $p->name,
                    'color'    => $p->color,
                    'deadline' => $p->deadline,
                ]),
                'sessions' => $sessions->map(fn ($s) => [
                    'id'         => $s->id,
                    'project'    => $s->project?->name,
                    'started_at' => $s->started_at->toIso8601String(),
                    'ended_at'   => $s->ended_at->toIso8601String(),
                    'duration_s' => $s->duration_seconds,
                    'note'       => $s->note,
                ]),
            ];

            return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="freelance-export.json"',
            ]);
        }

        $csv = "Date,Project,Start,End,Duration (h:mm),Note\n";
        foreach ($sessions as $s) {
            $hours    = intdiv($s->duration_seconds ?? 0, 3600);
            $minutes  = intdiv(($s->duration_seconds ?? 0) % 3600, 60);
            $csv .= sprintf("%s,%s,%s,%s,%d:%02d,%s\n",
                $s->started_at->format('Y-m-d'),
                $this->escapeCsv($s->project?->name ?? ''),
                $s->started_at->format('H:i'),
                $s->ended_at->format('H:i'),
                $hours,
                $minutes,
                $this->escapeCsv($s->note ?? ''),
            );
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="freelance-export.csv"',
        ]);
    }

    public function lms(Request $request): Response
    {
        $format = $request->query('format', 'csv');

        $courses     = LmsCourse::orderBy('name')->get();
        $assignments = LmsAssignment::with('course')->orderBy('due_at')->get();
        $grades      = LmsGrade::with('course')->get();

        if ($format === 'json') {
            $data = [
                'exported_at' => now()->toIso8601String(),
                'courses'     => $courses->map(fn ($c) => [
                    'id'   => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                ]),
                'assignments' => $assignments->map(fn ($a) => [
                    'id'      => $a->id,
                    'course'  => $a->course?->name,
                    'name'    => $a->name,
                    'due_at'  => $a->due_at,
                    'points'  => $a->points,
                ]),
                'grades' => $grades->map(fn ($g) => [
                    'course'      => $g->course?->name,
                    'final_grade' => $g->final_grade,
                ]),
            ];

            return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="lms-export.json"',
            ]);
        }

        $csv = "Course,Assignment,Due Date,Points\n";
        foreach ($assignments as $a) {
            $csv .= sprintf("%s,%s,%s,%s\n",
                $this->escapeCsv($a->course?->name ?? ''),
                $this->escapeCsv($a->name),
                $a->due_at ?? '',
                $a->points ?? '',
            );
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="lms-export.csv"',
        ]);
    }

    public function memories(Request $request): Response
    {
        $memories = AiMemory::orderBy('created_at')->get();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'memories'    => $memories->map(fn ($m) => [
                'id'         => $m->id,
                'content'    => $m->content,
                'category'   => $m->category,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
        ];

        return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => 'attachment; filename="ai-memories-export.json"',
        ]);
    }

    public function all(): Response
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'vektron_export_');
        $zip     = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::OVERWRITE);

        // Finance
        $entries    = FinanceEntry::with('category')->orderBy('date')->orderBy('created_at')->get();
        $categories = FinanceCategory::orderBy('name')->get();
        $finCsv     = "Date,Amount,Type,Description,Category,Source\n";
        foreach ($entries as $e) {
            $finCsv .= sprintf("%s,%.2f,%s,%s,%s,%s\n",
                $e->date, $e->amount,
                $e->amount >= 0 ? 'income' : 'expense',
                $this->escapeCsv($e->description ?? ''),
                $this->escapeCsv($e->category?->name ?? ''),
                $e->source ?? '',
            );
        }
        $zip->addFromString('finance.csv', $finCsv);
        $zip->addFromString('finance.json', json_encode([
            'exported_at' => now()->toIso8601String(),
            'categories'  => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color]),
            'entries'     => $entries->map(fn ($e) => [
                'id' => $e->id, 'date' => $e->date, 'amount' => $e->amount,
                'type' => $e->amount >= 0 ? 'income' : 'expense',
                'description' => $e->description, 'category' => $e->category?->name,
                'source' => $e->source, 'created_at' => $e->created_at?->toIso8601String(),
            ]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Tasks
        $boards  = Board::with(['columns.tasks'])->get();
        $taskCsv = "Board,Column,Title,Description,Priority,Deadline,Archived,Created\n";
        foreach ($boards as $board) {
            foreach ($board->columns->sortBy('position') as $col) {
                foreach ($col->tasks->sortBy('position') as $t) {
                    $taskCsv .= sprintf("%s,%s,%s,%s,%s,%s,%s,%s\n",
                        $this->escapeCsv($board->name), $this->escapeCsv($col->name),
                        $this->escapeCsv($t->title), $this->escapeCsv($t->description ?? ''),
                        $t->priority ?? '', $t->deadline ?? '',
                        $t->archived ? 'yes' : 'no', $t->created_at?->format('Y-m-d') ?? '',
                    );
                }
            }
        }
        $zip->addFromString('tasks.csv', $taskCsv);
        $zip->addFromString('tasks.json', json_encode([
            'exported_at' => now()->toIso8601String(),
            'boards' => $boards->map(fn ($b) => [
                'id' => $b->id, 'name' => $b->name, 'description' => $b->description,
                'columns' => $b->columns->sortBy('position')->values()->map(fn ($col) => [
                    'id' => $col->id, 'name' => $col->name,
                    'tasks' => $col->tasks->sortBy('position')->values()->map(fn ($t) => [
                        'id' => $t->id, 'title' => $t->title, 'description' => $t->description,
                        'priority' => $t->priority, 'deadline' => $t->deadline,
                        'archived' => (bool) $t->archived, 'created_at' => $t->created_at?->toIso8601String(),
                    ]),
                ]),
            ]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Work sessions
        $workSessions = WorkSession::whereNotNull('checked_out_at')->orderBy('checked_in_at')->get();
        $workCsv      = "Date,Check-in,Check-out,Duration (h:mm)\n";
        foreach ($workSessions as $s) {
            $total = ($s->duration_minutes ?? 0) * 60;
            $workCsv .= sprintf("%s,%s,%s,%d:%02d\n",
                $s->checked_in_at->format('Y-m-d'), $s->checked_in_at->format('H:i'),
                $s->checked_out_at->format('H:i'), intdiv($total, 3600), intdiv($total % 3600, 60),
            );
        }
        $zip->addFromString('work-sessions.csv', $workCsv);
        $zip->addFromString('work-sessions.json', json_encode([
            'exported_at' => now()->toIso8601String(),
            'sessions' => $workSessions->map(fn ($s) => [
                'id' => $s->id, 'date' => $s->checked_in_at->format('Y-m-d'),
                'checked_in_at' => $s->checked_in_at->toIso8601String(),
                'checked_out_at' => $s->checked_out_at->toIso8601String(),
                'duration_min' => $s->duration_minutes, 'note' => $s->note,
            ]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Freelance
        $flProjects = FreelanceProject::orderBy('name')->get();
        $flSessions = FreelanceSession::with('project')->whereNotNull('ended_at')->orderBy('started_at')->get();
        $flCsv      = "Date,Project,Start,End,Duration (h:mm),Note\n";
        foreach ($flSessions as $s) {
            $h = intdiv($s->duration_seconds ?? 0, 3600);
            $m = intdiv(($s->duration_seconds ?? 0) % 3600, 60);
            $flCsv .= sprintf("%s,%s,%s,%s,%d:%02d,%s\n",
                $s->started_at->format('Y-m-d'), $this->escapeCsv($s->project?->name ?? ''),
                $s->started_at->format('H:i'), $s->ended_at->format('H:i'), $h, $m,
                $this->escapeCsv($s->note ?? ''),
            );
        }
        $zip->addFromString('freelance.csv', $flCsv);
        $zip->addFromString('freelance.json', json_encode([
            'exported_at' => now()->toIso8601String(),
            'projects' => $flProjects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'color' => $p->color, 'deadline' => $p->deadline]),
            'sessions' => $flSessions->map(fn ($s) => [
                'id' => $s->id, 'project' => $s->project?->name,
                'started_at' => $s->started_at->toIso8601String(),
                'ended_at' => $s->ended_at->toIso8601String(),
                'duration_s' => $s->duration_seconds, 'note' => $s->note,
            ]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // LMS
        $lmsCourses     = \App\Models\LmsCourse::orderBy('name')->get();
        $lmsAssignments = LmsAssignment::with('course')->orderBy('due_at')->get();
        $lmsGrades      = LmsGrade::with('course')->get();
        $lmsCsv         = "Course,Assignment,Due Date,Points\n";
        foreach ($lmsAssignments as $a) {
            $lmsCsv .= sprintf("%s,%s,%s,%s\n",
                $this->escapeCsv($a->course?->name ?? ''), $this->escapeCsv($a->name),
                $a->due_at ?? '', $a->points ?? '',
            );
        }
        $zip->addFromString('lms.csv', $lmsCsv);
        $zip->addFromString('lms.json', json_encode([
            'exported_at' => now()->toIso8601String(),
            'courses'     => $lmsCourses->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'code' => $c->code]),
            'assignments' => $lmsAssignments->map(fn ($a) => ['id' => $a->id, 'course' => $a->course?->name, 'name' => $a->name, 'due_at' => $a->due_at, 'points' => $a->points]),
            'grades'      => $lmsGrades->map(fn ($g) => ['course' => $g->course?->name, 'final_grade' => $g->final_grade]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // AI Memories
        $memories = AiMemory::orderBy('created_at')->get();
        $zip->addFromString('ai-memories.json', json_encode([
            'exported_at' => now()->toIso8601String(),
            'memories' => $memories->map(fn ($m) => [
                'id' => $m->id, 'content' => $m->content,
                'category' => $m->category, 'created_at' => $m->created_at?->toIso8601String(),
            ]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->close();

        $content  = file_get_contents($tmpFile);
        unlink($tmpFile);
        $filename = 'vektron-export-' . now()->format('Y-m-d') . '.zip';

        return response($content, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function escapeCsv(string $value): string
    {
        $value = str_replace(['"', "\n", "\r"], [' ', ' ', ''], $value);
        return '"' . $value . '"';
    }
}
