<?php

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


//  API routes version 1
Route::prefix('v1')->group(function () {
    // Add your v1 API routes here



    Route::get('/health', [HealthController::class, 'check']);


    // Authenticated routes
    Route::post('/register', []);
    // Route::post('/login', []);
    // Route::post('/logout', []);
    // Route::post('/', []);
});


// Handle Method Not Allowed (405) for existing routes with wrong methods
Route::any('{any}', [FallbackController::class, 'methodNotAllowed'])
    ->where('any', '.*');

// Handle Not Found (404) for all other undefined routes
Route::fallback([FallbackController::class, '__invoke']);
