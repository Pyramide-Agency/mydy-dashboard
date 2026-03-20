<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\FinanceCategoryController;
use App\Http\Controllers\FinanceEntryController;
use App\Http\Controllers\FinanceSummaryController;
use App\Http\Controllers\LmsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\FreelanceController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\WorkController;
use Illuminate\Support\Facades\Route;

// Health check (used by Docker HEALTHCHECK and Dokploy)
Route::get('/health', fn () => response()->json(['ok' => true]));

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);

// Telegram webhook (must be public, no auth, no throttle)
Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])
    ->withoutMiddleware('throttle');

// Work webhook (public, key-based auth, no throttle)
Route::post('/work/webhook', [WorkController::class, 'webhook'])
    ->withoutMiddleware('throttle');

// Protected routes
Route::middleware('auth.api')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/check',  [AuthController::class, 'check']);

    // Boards
    Route::get('/boards',            [BoardController::class, 'index']);
    Route::post('/boards',           [BoardController::class, 'store']);
    Route::get('/boards/{board}',    [BoardController::class, 'show']);
    Route::put('/boards/{board}',    [BoardController::class, 'update']);
    Route::delete('/boards/{board}', [BoardController::class, 'destroy']);

    // Columns (nested under board)
    Route::get('/boards/{board}/columns',  [ColumnController::class, 'index']);
    Route::post('/boards/{board}/columns', [ColumnController::class, 'store']);
    Route::put('/columns/{column}',        [ColumnController::class, 'update']);
    Route::delete('/columns/{column}',     [ColumnController::class, 'destroy']);

    // Tasks — static routes BEFORE {task} wildcard
    Route::get('/tasks/archived',      [TaskController::class, 'archived']);
    Route::post('/tasks/archive-done', [TaskController::class, 'archiveDone']);
    Route::post('/tasks',              [TaskController::class, 'store']);
    Route::put('/tasks/{task}',        [TaskController::class, 'update']);
    Route::delete('/tasks/{task}',     [TaskController::class, 'destroy']);
    Route::post('/tasks/{task}/move',  [TaskController::class, 'move']);

    // Finance entries
    Route::get('/finance/entries',             [FinanceEntryController::class, 'index']);
    Route::post('/finance/entries',            [FinanceEntryController::class, 'store']);
    Route::put('/finance/entries/{entry}',     [FinanceEntryController::class, 'update']);
    Route::delete('/finance/entries/{entry}',  [FinanceEntryController::class, 'destroy']);

    // Finance categories
    Route::get('/finance/categories',               [FinanceCategoryController::class, 'index']);
    Route::post('/finance/categories',              [FinanceCategoryController::class, 'store']);
    Route::put('/finance/categories/{category}',    [FinanceCategoryController::class, 'update']);
    Route::delete('/finance/categories/{category}', [FinanceCategoryController::class, 'destroy']);

    // Finance summary & AI
    Route::get('/finance/summary',               [FinanceSummaryController::class, 'index']);
    Route::post('/finance/ai-feedback',          [AiController::class, 'feedback']);
    Route::get('/finance/conversations',         [AiController::class, 'listConversations']);
    Route::post('/finance/conversations',        [AiController::class, 'createConversation']);
    Route::delete('/finance/conversations/{id}', [AiController::class, 'deleteConversation']);
    Route::get('/finance/ai-conversation',       [AiController::class, 'getConversation']);
    Route::post('/finance/ai-conversation',      [AiController::class, 'sendMessage']);

    // AI memories
    Route::get('/ai/memories',          [AiController::class, 'listMemories']);
    Route::post('/ai/memories',         [AiController::class, 'storeMemory']);
    Route::delete('/ai/memories/{id}',  [AiController::class, 'deleteMemory']);
    Route::delete('/ai/memories',       [AiController::class, 'clearMemories']);

    // Settings
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::post('/settings/test-deadline-notification', [SettingsController::class, 'testDeadlineNotification']);

    // Telegram registration
    Route::post('/telegram/register', [TelegramController::class, 'register']);

    // LMS (Canvas)
    Route::get('/lms/status',                           [LmsController::class, 'status']);
    Route::post('/lms/sync',                            [LmsController::class, 'sync']);
    Route::get('/lms/courses',                          [LmsController::class, 'courses']);
    Route::get('/lms/courses/{course}',                 [LmsController::class, 'course']);
    Route::patch('/lms/courses/{course}',               [LmsController::class, 'updateCourse']);
    Route::get('/lms/courses/{course}/timeline',        [LmsController::class, 'courseTimeline']);
    Route::get('/lms/assignments',                      [LmsController::class, 'assignments']);
    Route::get('/lms/deadlines',                        [LmsController::class, 'deadlines']);
    Route::get('/lms/calendar',                         [LmsController::class, 'calendar']);
    Route::get('/lms/grades',                           [LmsController::class, 'grades']);
    Route::get('/lms/announcements',                    [LmsController::class, 'announcements']);
    Route::patch('/lms/announcements/{announcement}/read', [LmsController::class, 'markAnnouncementRead']);

    // Work tracker
    Route::get('/work/status',                  [WorkController::class, 'status']);
    Route::get('/work/sessions',                [WorkController::class, 'sessions']);
    Route::get('/work/stats',                   [WorkController::class, 'stats']);
    Route::post('/work/checkin',                [WorkController::class, 'checkin']);
    Route::post('/work/checkout',               [WorkController::class, 'checkout']);
    Route::patch('/work/sessions/{session}',    [WorkController::class, 'update']);
    Route::delete('/work/sessions/{session}',   [WorkController::class, 'destroy']);
    Route::post('/work/webhook-enabled',        [WorkController::class, 'setEnabled']);
    Route::get('/work/webhook-info',            [WorkController::class, 'webhookInfo']);
    Route::post('/work/webhook-key/regenerate', [WorkController::class, 'regenerateKey']);
    Route::delete('/work/webhook',              [WorkController::class, 'revokeWebhook']);

    // Freelance time tracker — static routes BEFORE wildcard
    Route::get('/freelance/sessions/active',        [FreelanceController::class, 'activeSession']);
    Route::post('/freelance/sessions/start',        [FreelanceController::class, 'startTimer']);
    Route::post('/freelance/sessions/stop',         [FreelanceController::class, 'stopTimer']);
    Route::post('/freelance/sessions/pause',        [FreelanceController::class, 'pauseTimer']);
    Route::post('/freelance/sessions/resume',       [FreelanceController::class, 'resumeTimer']);
    Route::get('/freelance/sessions',               [FreelanceController::class, 'sessions']);
    Route::post('/freelance/sessions',              [FreelanceController::class, 'createSessionManual']);
    Route::patch('/freelance/sessions/{session}',   [FreelanceController::class, 'updateSession']);
    Route::delete('/freelance/sessions/{session}',  [FreelanceController::class, 'deleteSession']);

    Route::get('/freelance/projects',               [FreelanceController::class, 'projects']);
    Route::post('/freelance/projects',              [FreelanceController::class, 'createProject']);
    Route::put('/freelance/projects/{project}',     [FreelanceController::class, 'updateProject']);
    Route::delete('/freelance/projects/{project}',  [FreelanceController::class, 'deleteProject']);

    Route::get('/freelance/stats',                  [FreelanceController::class, 'stats']);
    Route::get('/freelance/export',                 [FreelanceController::class, 'export']);

    // Data export
    Route::get('/export/all',       [ExportController::class, 'all']);
    Route::get('/export/finance',   [ExportController::class, 'finance']);
    Route::get('/export/tasks',     [ExportController::class, 'tasks']);
    Route::get('/export/work',      [ExportController::class, 'work']);
    Route::get('/export/freelance', [ExportController::class, 'freelance']);
    Route::get('/export/lms',       [ExportController::class, 'lms']);
    Route::get('/export/memories',  [ExportController::class, 'memories']);
});
