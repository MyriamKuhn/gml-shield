<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public login endpoint with rate limiting.
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// Protected endpoints requiring authentication.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);
});
