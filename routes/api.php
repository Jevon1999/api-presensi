<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\ProgressController;

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

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/me',[AuthController::class, 'me']);

    //crud resources
    Route::apiResource('offices', OfficeController::class);
    Route::apiResource('members', MemberController::class);
    Route::apiResource('progresses', ProgressController::class);

    // attendance endpoints
    Route::get('/attendances', [App\Http\Controllers\Api\AttendanceController::class, 'index']);
    Route::get('/attendances/{attendance}', [App\Http\Controllers\Api\AttendanceController::class, 'show']);
    Route::post('/attendances/{attendance}/reset', [App\Http\Controllers\Api\AttendanceController::class, 'reset']);
    Route::get('/attendances/report', [App\Http\Controllers\Api\AttendanceController::class, 'report']);
});

Route::middleware('bot.auth')->group(function () {
Route::post('/attendances/check-in', [App\Http\Controllers\Api\AttendanceController::class, 'checkIn']);
Route::post('/attendances/check-out', [App\Http\Controllers\Api\AttendanceController::class, 'checkOut']);
});


