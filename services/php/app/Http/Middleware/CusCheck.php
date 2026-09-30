<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CusCheck
{
    public function handle(Request $request, Closure $next): Response
    {
        // Logic test: Nếu trên URL không có parameter ?check=1 thì chặn lại
        if ($request->query('check') !== '1') {
            return response('Access Denied: Bạn chưa qua được CusCheck Middleware!', 403);
        }

        return $next($request);
    }
}
