<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware('cus.check');

Route::get('/users/{user}', [UserController::class, 'index']);
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/run-setup', function () {
    Artisan::call('migrate', ['--force' => true]);
    $migrate = Artisan::output();

    Artisan::call('storage:link');
    $link = Artisan::output();

    return response("<pre>{$migrate}\n{$link}</pre>");
});
 