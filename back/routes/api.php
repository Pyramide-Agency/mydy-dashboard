<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\FinanceCategoryController;
use App\Http\Controllers\FinanceEntryController;
use App\Http\Controllers\FinanceSummaryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

// Health check (used by Docker HEALTHCHECK and Dokploy)
Route::get('/health', fn () => response()->json(['ok' => true]));

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);

// Telegram webhook (must be public, no auth, no throttle)
Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])
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
    Route::get('/finance/summary',          [FinanceSummaryController::class, 'index']);
    Route::post('/finance/ai-feedback',     [AiController::class, 'feedback']);
    Route::get('/finance/ai-conversation',  [AiController::class, 'getConversation']);
    Route::post('/finance/ai-conversation', [AiController::class, 'sendMessage']);

    // Settings
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);

    // Telegram registration
    Route::post('/telegram/register', [TelegramController::class, 'register']);
});
