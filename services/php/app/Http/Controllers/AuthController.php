<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerfifyCodeRegister;
class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $input = $request->validated();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        return response()->json([
            'message' => 'User created successfully',
        ]);
    }

    public function login(LoginRequest $request)
    {
        $input = $request->validated();

        $user = User::where('email', $input['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'email or password incorrect',
            ], 401);
        }

        if (!Hash::check($input['password'], $user->password)) {
            return response()->json([
                'message' => 'email or password incorrect',
            ], 401);
        }

        $accessToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
        ]);
    }

    public function getUser(Request $request)
    {
        $user = $request->user();

        dd($user);
        return response()->json($user);
    }
    public function resend(ResendRequest $request)
{
    $input = $request->validated();

    $user = User::where('email', $input['email'])->first();

    if ($user->email_verified_at) {
        return response()->json([
            'message' => 'Email already verified',
        ], 400);
    }

    $code = random_int(100000, 999999);

    $user->update([
        'code_verify_email' => Hash::make((string) $code),
        'expired_verify_email' => now()->addMinutes(10),
    ]);

    Mail::to($user->email)->queue(
        new VerfifyCodeRegister($code, $user->name)
    );

    return response()->json([
        'message' => 'Verification email resent successfully',
    ], 200);
}
}
