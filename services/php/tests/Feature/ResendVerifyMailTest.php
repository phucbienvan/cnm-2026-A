<?php

namespace Tests\Feature;

use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendVerifyMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_can_resend_verification_email(): void
    {
        Mail::fake();

        $now = now();
        $this->travelTo($now);

        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => $now->copy()->subMinute(),
        ]);

        $response = $this->postJson('/api/resend-verify-mail', [
            'email' => $user->email,
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Email xác thực đã được gửi lại thành công.',
        ]);

        $user->refresh();

        self::assertSame(
            $now->copy()->addMinutes(10)->toDateTimeString(),
            $user->expired_verify_email->toDateTimeString(),
        );

        Mail::assertQueued(VerfifyCodeRegister::class, function (VerfifyCodeRegister $mail) use ($user): bool {
            self::assertMatchesRegularExpression('/\A\d{6}\z/', $mail->code);
            self::assertTrue(Hash::check($mail->code, $user->code_verify_email));

            return $mail->hasTo($user->email);
        });
    }

    public function test_verified_user_cannot_resend_verification_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/resend-verify-mail', [
            'email' => $user->email,
        ]);

        $response->assertStatus(400)->assertJson([
            'message' => 'Email đã được xác thực rồi, không cần gửi lại.',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_resend_requires_an_existing_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/resend-verify-mail', [
            'email' => 'missing@example.com',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');

        Mail::assertNothingQueued();
    }
}
