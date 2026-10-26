<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\UserController;

// Public login endpoint with rate limiting.
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// Protected endpoints requiring authentication.
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // List drivers for mission assignment.
    // Role-based authorization is enforced by DriverController.
    Route::get('/drivers', [DriverController::class, 'index']);

    // Read-only audit logs, restricted to active administrators.
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // User administration, restricted to active administrators.
    Route::get('/users', [UserController::class, 'index']);
    // Create driver or dispatcher accounts (ADMIN only).
    Route::post('/users', [UserController::class, 'store']);
    // Activate or deactivate a driver or dispatcher account.
    Route::patch('/users/{user}/active', [UserController::class, 'updateActive']);
});

// Mission management routes — authentication required.
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/missions', [MissionController::class, 'index']);
    Route::post('/missions', [MissionController::class, 'store']);
    Route::patch('/missions/{mission}', [MissionController::class, 'update']);
    Route::get('/missions/{mission}', [MissionController::class, 'show']);

    Route::patch('/missions/{mission}/assign', [MissionController::class, 'assign']);
    Route::patch('/missions/{mission}/status', [MissionController::class, 'updateStatus']);
    Route::patch('/missions/{mission}/cancel', [MissionController::class, 'cancel']);

    Route::get('/missions/{mission}/secret-code', [MissionController::class, 'revealSecret']);


    // Upload delivery proofs by the assigned driver.
    Route::post('/missions/{mission}/documents', [DocumentController::class, 'store']);
    // List delivery proof metadata for the owning dispatcher.
    Route::get('/missions/{mission}/documents', [DocumentController::class, 'index']);
    // Download a private delivery proof after authorization.
    Route::get('/missions/{mission}/documents/{document}', [DocumentController::class, 'download']);

});
