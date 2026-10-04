<?php

/**
 * @author Võ Đức Phú
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CusCheck
{
    /**
     * Handle an incoming request.
     *
     * Middleware tùy chỉnh – hiện tại cho phép tất cả request đi qua.
     * Mở rộng logic kiểm tra tại đây khi cần.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
