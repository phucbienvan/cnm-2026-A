<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CusCheck
{
<<<<<<< HEAD
    public function handle(Request $request, Closure $next): Response
    {
        // Logic test: Nếu trên URL không có parameter ?check=1 thì chặn lại
        if ($request->query('check') !== '1') {
            return response('Access Denied: Bạn chưa qua được CusCheck Middleware!', 403);
        }

=======
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
>>>>>>> 8573623d1ed2f3612db4e887d436b72d6d9c3247
        return $next($request);
    }
}
