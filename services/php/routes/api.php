<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('verify-mail', [AuthController::class, 'verifyMail']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('users', [AuthController::class, 'getUser']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{post}', [PostController::class, 'update'])
        ->missing(function () {
            return response()->json([
                'message' => 'Không tìm thấy bài viết',
            ], 404);
        });
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->missing(function () {
        return response()->json([
            'message' => 'Không tìm thấy bài viết',
        ], 404);
    });
});

Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show'])->missing(function () {
    return response()->json([
        'message' => 'Không tìm thấy bài viết',
    ], 404);
});
