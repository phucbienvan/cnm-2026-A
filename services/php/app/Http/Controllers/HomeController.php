<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Hiển thị trang chủ của ứng dụng.
     */
    public function index(): View
    {
        return view('home');
    }
}

