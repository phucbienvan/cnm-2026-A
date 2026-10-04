<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * Hiển thị thông tin người dùng cùng lời chào.
     */
    public function index(User $user): JsonResponse
    {
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'message' => 'Hello',
        ], Response::HTTP_OK);
    }
}



