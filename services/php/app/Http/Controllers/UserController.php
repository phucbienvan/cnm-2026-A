<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateRequest;
use App\Models\User;

class UserController extends Controller
{
    public function index(User $user)
    {        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'message' => 'Hello',
        ]);
    }

    public function update(UpdateRequest $request, User $user)
    {
        $user->update($request->validated());

        return response()->json([
            'message' => 'Cập nhật người dùng thành công',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ], 200);
    }
}
