<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\LetterController;
use App\Http\Controllers\Api\DispositionController;
use App\Http\Controllers\Api\DashboardController;

// Auth Routes
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1'); // max 5 attempts per minute per IP

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // User Management (Admin only)
    Route::apiResource('users', UserController::class);

    // Letter Management
    Route::get('/letters/{letter}/track', [LetterController::class, 'track']);
    Route::post('/letters/{letter}/escalate', [LetterController::class, 'escalate']);
    Route::put('/letters/{letter}/archive', [LetterController::class, 'archive']);
    Route::apiResource('letters', LetterController::class);

    // Disposition Management
    Route::get('/dispositions/incoming', [DispositionController::class, 'incoming']);
    Route::get('/dispositions/outgoing', [DispositionController::class, 'outgoing']);
    Route::post('/dispositions', [DispositionController::class, 'store']);
    Route::get('/dispositions/{disposition}', [DispositionController::class, 'show']);
    Route::put('/dispositions/{disposition}/read', [DispositionController::class, 'markAsRead']);
    Route::put('/dispositions/{disposition}/accept', [DispositionController::class, 'accept']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
