<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::get('/test-cus', function () {
    return 'Chúc mừng! Bạn đã đi qua CusCheck Middleware thành công.';
})->middleware('cus.check');

Route::get('/users/{user}', [UserController::class, 'index']);
