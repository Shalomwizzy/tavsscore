<?php

use App\Http\Controllers\Api\BookingWorkerController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\PredictionController;
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

Route::get('/matches/live', [MatchController::class, 'live']);
Route::get('/matches/today', [MatchController::class, 'today']);
Route::get('/matches/finished', [MatchController::class, 'finished']);
Route::get('/predictions', [PredictionController::class, 'index']);
Route::get('/predictions/{match_id}', [PredictionController::class, 'show'])->whereNumber('match_id');
// Translation endpoints — rate-limited to protect Groq spend
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/predictions/{prediction}/translate', [TranslationController::class, 'translate'])->whereNumber('prediction');
    Route::post('/blog/{post}/translate', [TranslationController::class, 'translateBlog'])->whereNumber('post');
});

// Booking-code automation worker (external, token-authenticated).
Route::middleware('worker.token')->prefix('worker')->group(function () {
    Route::get('/betslip-spec', [BookingWorkerController::class, 'spec']);
    Route::get('/booking-generation-request', [BookingWorkerController::class, 'generationRequest']);
    Route::post('/booking-generation-request/complete', [BookingWorkerController::class, 'completeGenerationRequest']);
    Route::post('/booking-codes', [BookingWorkerController::class, 'store']);
});
