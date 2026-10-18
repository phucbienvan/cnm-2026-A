<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendMailRequest;
use App\Http\Requests\Auth\VerfiyMailRequest;
use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $input = $request->validated();
        $code = rand(100000, 999999);
        $expired_at = now()->addMinutes(10);

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'code_verify_email' => Hash::make($code),
            'expired_verify_email' => $expired_at,
        ]);

        Mail::to($user->email)->queue(new VerfifyCodeRegister($code, $user->name));

        return response()->json([
            'message' => 'User created successfully',
        ]);
    }

    public function verifyMail(VerfiyMailRequest $request)
    {
        $input = $request->validated();
        $user = User::where('email', $input['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'email not found',
            ], 404);
        }

        if ($user->expired_verify_email < now()) {
            return response()->json([
                'message' => 'code is expired',
            ], 401);
        }

        if (! Hash::check($input['code'], $user->code_verify_email)) {
            return response()->json([
                'message' => 'code is incorrect',
            ], 401);
        }

        $user->update([
            'email_verified_at' => now(),
            'code_verify_email' => null,
            'expired_verify_email' => null,
        ]);

        return response()->json([
            'message' => 'email verified successfully',
        ]);
    }

    public function resendMail(ResendMailRequest $request)
    {
        $input = $request->validated();
        $user = User::where('email', $input['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'email not found',
            ], 404);
        }

        if (! Cache::add('resend-mail:'.$user->id, true, now()->addSeconds(60))) {
            return response()->json([
                'message' => 'verification email was sent recently',
            ], 429);
        }

        $code = rand(100000, 999999);
        $expired_at = now()->addMinutes(10);

        $user->update([
            'code_verify_email' => Hash::make($code),
            'expired_verify_email' => $expired_at,
        ]);

        Mail::to($user->email)->queue(new VerfifyCodeRegister($code, $user->name));

        return response()->json([
            'message' => 'verification email sent',
        ]);
    }

    public function login(LoginRequest $request)
    {
        $input = $request->validated();

        $user = User::where('email', $input['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'email or password incorrect',
            ], 401);
        }

        if (! $user->email_verified_at) {
            return response()->json([
                'message' => 'email not verified',
            ], 401);
        }

        if (! Hash::check($input['password'], $user->password)) {
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

        return response()->json($user);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
