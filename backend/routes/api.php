<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\DriverController;

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
});
