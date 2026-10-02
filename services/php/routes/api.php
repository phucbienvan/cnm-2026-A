<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\UserController;

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

Route::put('/posts/{post}', [PostController::class, 'update']);

Route::get('/users', function () {
    return response()->json([
        'data' => User::all(),
        'message' => 'Lấy danh sách người dùng thành công'
    ]);
});

Route::put('/users/{id}', [UserController::class, 'update']);


