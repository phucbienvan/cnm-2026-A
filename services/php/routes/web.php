<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware('cus.check');

Route::get('/users/{user}', [UserController::class, 'index']);
 