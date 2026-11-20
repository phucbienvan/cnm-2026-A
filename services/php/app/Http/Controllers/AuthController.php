<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerfiyMailRequest;
use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $input = $request->validated();
        $code = rand(100000, 999999);
        $expiredAt = now()->addMinutes(10);

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'code_verify_email' => Hash::make($code),
            'expired_verify_email' => $expiredAt,
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

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ], 409);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent.',
        ]);
    }

    public function verifyEmail(string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Invalid verification link.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json([
            'message' => 'Email verified successfully.',
        ]);
    }

    public function getUser(Request $request)
    {
        return response()->json($request->user());
    }
}
