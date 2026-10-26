<?php

namespace App\Http\Controllers;

use App\Models\User;
<<<<<<< HEAD
=======
use Illuminate\Http\Request;
>>>>>>> 8573623d1ed2f3612db4e887d436b72d6d9c3247

class UserController extends Controller
{
    public function index(User $user)
<<<<<<< HEAD
    {
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
=======
    {        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'message' => 'Hello',
>>>>>>> 8573623d1ed2f3612db4e887d436b72d6d9c3247
        ]);
    }
}
