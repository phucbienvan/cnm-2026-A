<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-KEY');

        if ($apiKey !== 'secret123') {
            return response()->json([
                'status' => 'error',
                'message' => 'API key không hợp lệ hoặc bị thiếu',
            ], 401);
        }

        return $next($request);
    }
}
