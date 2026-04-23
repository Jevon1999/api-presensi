<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BotConfigController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\HolidayController;

use App\Http\Controllers\Api\MemberApplicationController;
use App\Http\Controllers\Api\MemberDashboardController;

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

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::post('/waha/webhook', [App\Http\Controllers\WahaWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/me',[AuthController::class, 'me']);

    // Member application (for role=user)
    Route::post('/member/apply', [MemberApplicationController::class, 'apply']);
    Route::get('/member/my-status', [MemberApplicationController::class, 'myStatus']);

    // Member own dashboard (for approved members)
    Route::get('/member/dashboard', [MemberDashboardController::class, 'dashboard']);
    Route::get('/member/progress', [MemberDashboardController::class, 'progress']);
    Route::get('/member/report', [MemberDashboardController::class, 'report']);

    // Admin: specific operations
    Route::get('/users/available-for-member', [UserController::class, 'availableForMember']);
    Route::get('/members/pending-count', [MemberApplicationController::class, 'pendingCount']);

    // Admin: approve/reject member applications
    Route::put('/members/{id}/approve', [MemberApplicationController::class, 'approve']);
    Route::put('/members/{id}/reject', [MemberApplicationController::class, 'reject']);

    //crud resources
    Route::apiResource('offices', OfficeController::class);
    Route::apiResource('members', MemberController::class);
    Route::apiResource('progresses', ProgressController::class);
    Route::apiResource('users', UserController::class);

    // attendance endpoints — route spesifik HARUS sebelum wildcard {attendance}
    Route::get('/attendances', [App\Http\Controllers\Api\AttendanceController::class, 'index']);
    Route::get('/attendances/report', [App\Http\Controllers\Api\AttendanceController::class, 'report']);
    Route::get('/attendances/{attendance}', [App\Http\Controllers\Api\AttendanceController::class, 'show']);
    Route::post('/attendances/{attendance}/reset', [App\Http\Controllers\Api\AttendanceController::class, 'reset']);

    // Statistics (admin dashboard)
    Route::get('/statistics', [StatisticsController::class, 'index']);

    // Bot config (singleton)
    Route::get('/bot-configs', [BotConfigController::class, 'index']);
    Route::put('/bot-configs', [BotConfigController::class, 'update']);

    // Holidays — hari libur nasional
    Route::get('/holidays', [HolidayController::class, 'index']);
    Route::post('/holidays/sync', [HolidayController::class, 'sync']);
    Route::post('/holidays', [HolidayController::class, 'store']);
    Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy']);
    Route::get('/working-day/status', [HolidayController::class, 'dayStatus']);
});

Route::middleware('bot.auth')->group(function () {
Route::post('/attendances/check-in', [App\Http\Controllers\Api\AttendanceController::class, 'checkIn']);
Route::post('/attendances/check-out', [App\Http\Controllers\Api\AttendanceController::class, 'checkOut']);
});


