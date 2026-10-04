<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::apiResource('posts', PostController::class)
    ->missing(function () {
        return response()->json([
            'message' => 'Không tìm thấy bài viết',
        ], 404);
    });

