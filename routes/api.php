<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DrugController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Public Authentication Routes
 * 
 * Rate limited to 5 requests per minute to prevent brute force attacks
 */
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

/**
 * Public Drug Search Routes
 * 
 * Rate limited to 60 requests per minute to prevent API abuse
 * No authentication required - accessible to all users
 */
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/drugs/search', [DrugController::class, 'search']);
});

/**
 * Protected Routes
 * 
 * Requires authentication via Sanctum token
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
