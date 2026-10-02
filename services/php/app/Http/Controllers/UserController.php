<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Không tìm thấy người dùng'
            ], 404);
        }

        $user->name = $request->name;
        $user->save();

        return response()->json([
            'data' => $user,
            'message' => 'Cập nhật người dùng thành công'
        ]);
    }
}
