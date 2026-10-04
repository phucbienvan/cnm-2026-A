<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CusCheck
{
    /**
     * Xử lý request qua CusCheck middleware.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
