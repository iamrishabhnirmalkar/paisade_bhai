<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Bill\BillController;
use App\Http\Controllers\Api\Group\SplitGroupController;
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

    // Protected Group Routes
    Route::middleware('auth:api')->prefix('groups')->group(function () {
        // Group CRUD
        Route::post('/', [SplitGroupController::class, 'create']);
        Route::get('/', [SplitGroupController::class, 'index']);
        Route::get('/my', [SplitGroupController::class, 'myGroups']);
        Route::get('/{id}', [SplitGroupController::class, 'show']);
        Route::put('/{id}', [SplitGroupController::class, 'update']);
        Route::delete('/{id}', [SplitGroupController::class, 'destroy']);

        // Member management
        Route::post('/{groupId}/members', [SplitGroupController::class, 'addMember']);
        Route::get('/{groupId}/members', [SplitGroupController::class, 'getMembers']);
        Route::delete('/{groupId}/members/{memberId}', [SplitGroupController::class, 'removeMember']);
    });





    // Protected Bill Routes
    Route::middleware('auth:api')->prefix('groups')->group(function () {
        // Bill management
        Route::post('/{groupId}/bills', [BillController::class, 'create']);
        Route::get('/{groupId}/bills', [BillController::class, 'index']);
        Route::get('/{groupId}/bills/{billId}', [BillController::class, 'show']);
        Route::put('/{groupId}/bills/{billId}', [BillController::class, 'update']);
        Route::delete('/{groupId}/bills/{billId}', [BillController::class, 'destroy']);

        // Balance endpoints
        Route::get('/{groupId}/balance', [BillController::class, 'getGroupBalance']);
        Route::get('/{groupId}/my-balance', [BillController::class, 'getMyBalance']);
    });
});

// Handle Method Not Allowed (405) for existing routes with wrong methods
Route::any('{any}', [FallbackController::class, 'methodNotAllowed'])
    ->where('any', '.*');

// Handle Not Found (404) for all other undefined routes
Route::fallback([FallbackController::class, '__invoke']);
