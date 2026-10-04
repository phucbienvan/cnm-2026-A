<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiKey
{
    /**
     * Khóa API mặc định dùng để xác thực request.
     */
    protected const EXPECTED_API_KEY = 'secret123';

    /**
     * Xử lý request đến: kiểm tra header X-API-KEY.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = $request->header('X-API-KEY');

        if ($providedKey !== self::EXPECTED_API_KEY) {
            return response()->json([
                'status' => 'error',
                'message' => 'API key không hợp lệ hoặc bị thiếu',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

