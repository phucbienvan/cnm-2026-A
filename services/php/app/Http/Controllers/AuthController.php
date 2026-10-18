<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendEmailRequest;
use App\Http\Requests\Auth\VerfiyMailRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Mail\VerfifyCodeRegister;
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

        if (!$user) {
            return response()->json([
                'message' => 'email not found',
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'email already verified',
            ], 400);
        }

        if (!$user->code_verify_email || !$user->expired_verify_email || $user->expired_verify_email < now()) {
            return response()->json([
                'message' => 'code is expired',
            ], 401);
        }

        if (!Hash::check($input['code'], $user->code_verify_email)) {
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

    public function resendEmail(ResendEmailRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();
        $response = ['message' => 'If the email needs verification, a new code will be sent.'];

        if (!$user || $user->email_verified_at) {
            return response()->json($response, 202);
        }

        $code = (string) random_int(100000, 999999);

        try {
            Mail::to($user->email)->send(new VerfifyCodeRegister($code, $user->name));

            $user->update([
                'code_verify_email' => Hash::make($code),
                'expired_verify_email' => now()->addMinutes(10),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Resend verification email failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to send verification email.',
            ], 500);
        }

        return response()->json($response, 202);
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

        if (!$user->email_verified_at) {
            return response()->json([
                'message' => 'email not verified',
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
