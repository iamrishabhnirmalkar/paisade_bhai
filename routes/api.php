<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\System\FallbackController;
use App\Http\Controllers\Api\System\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// API routes version 1
Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'check']);

    Route::prefix('auth')->group(function () {
        // Public routes
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // Protected routes
        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh_token']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });
});

// Handle Method Not Allowed (405) for existing routes with wrong methods
Route::any('{any}', [FallbackController::class, 'methodNotAllowed'])
    ->where('any', '.*');

// Handle Not Found (404) for all other undefined routes
Route::fallback([FallbackController::class, '__invoke']);
