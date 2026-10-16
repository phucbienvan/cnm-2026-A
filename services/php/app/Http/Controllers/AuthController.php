<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerfiyMailRequest;
use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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

    public function resendMail(LoginRequest $request): JsonResponse
    {
        $input = $request->validated();
        $codeSaved = false;

        try {
            return DB::transaction(function () use ($input, &$codeSaved): JsonResponse {
                $user = User::where('email', $input['email'])->lockForUpdate()->first();

                if (! $user || ! Hash::check($input['password'], $user->password)) {
                    return response()->json([
                        'message' => 'email or password incorrect',
                    ], 401);
                }

                if ($user->email_verified_at) {
                    return response()->json([
                        'message' => 'email already verified',
                    ], 409);
                }

                $retryAfter = $user->expired_verify_email
                    ? $user->expired_verify_email->copy()->subMinutes(10)->addMinute()->timestamp - now()->timestamp
                    : 0;

                if ($retryAfter > 0) {
                    return response()->json([
                        'message' => 'Too many requests. Please try again later.',
                    ], 429)->header('Retry-After', (string) $retryAfter);
                }

                do {
                    $code = $this->generateVerificationCode();
                } while ($user->code_verify_email && Hash::check($code, $user->code_verify_email));

                $user->update([
                    'code_verify_email' => Hash::make($code),
                    'expired_verify_email' => now()->addMinutes(10),
                ]);

                DB::afterCommit(function () use ($user, $code, &$codeSaved): void {
                    $codeSaved = true;
                    Mail::to($user->email)->queue(new VerfifyCodeRegister($code, $user->name));
                });

                return response()->json([
                    'message' => 'Verification email queued successfully',
                ], 202);
            });
        } catch (Throwable) {
            if ($codeSaved) {
                Log::error('Verification code saved, but verification email could not be queued.');

                return response()->json([
                    'message' => 'Verification code saved, but email could not be queued. Please retry after 60 seconds.',
                ], 503)->header('Retry-After', '60');
            }

            Log::error('Unable to save verification code.');

            return response()->json([
                'message' => 'Unable to save verification code. Please try again later.',
            ], 503);
        }
    }

    protected function generateVerificationCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    public function verifyMail(VerfiyMailRequest $request): JsonResponse
    {
        $input = $request->validated();

        return DB::transaction(function () use ($input): JsonResponse {
            $user = User::where('email', $input['email'])->lockForUpdate()->first();

            if (! $user) {
                return response()->json([
                    'message' => 'email not found',
                ], 404);
            }

            if ($user->email_verified_at) {
                return response()->json([
                    'message' => 'email already verified',
                ], 409);
            }

            if (! $user->expired_verify_email || $user->expired_verify_email->lessThanOrEqualTo(now())) {
                return response()->json([
                    'message' => 'code is expired',
                ], 401);
            }

            if (! $user->code_verify_email || ! Hash::check($input['code'], $user->code_verify_email)) {
                return response()->json([
                    'message' => 'code is incorrect',
                ], 401);
            }

            $updated = User::whereKey($user->id)
                ->whereNull('email_verified_at')
                ->where('code_verify_email', $user->code_verify_email)
                ->where('expired_verify_email', '>', now())
                ->update([
                    'email_verified_at' => now(),
                    'code_verify_email' => null,
                    'expired_verify_email' => null,
                ]);

            if ($updated !== 1) {
                return response()->json([
                    'message' => 'code is incorrect',
                ], 401);
            }

            return response()->json([
                'message' => 'email verified successfully',
            ]);
        });
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
