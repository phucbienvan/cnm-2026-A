<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

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
