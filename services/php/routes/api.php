<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('users/me', [UserController::class, 'getUser']);
});


Route::get('/posts', [PostController::class, 'index']);
Route::post('/posts', [PostController::class, 'store']);
Route::get('/posts/{post}', [PostController::class, 'show'])->missing(function () {
    return response()->json([
        'message' => 'Không tìm thấy bài viết',
    ], 404);
});

Route::delete('/posts/{post}', [PostController::class, 'destroy'])->missing(function () {
    return response()->json([
        'message' => 'Không tìm thấy bài viết',
    ], 404);
});

Route::put('/posts/{post}', [PostController::class, 'update'])->missing(function () {
    return response()->json([
        'message' => 'Không tìm thấy bài viết',
    ], 404);
});
