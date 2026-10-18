<?php

namespace Tests\Feature;

use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resends_a_verification_code_to_an_unverified_user(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/resend-mail', [
            'email' => $user->email,
        ]);

        $response->assertOk()->assertExactJson([
            'message' => 'verification code resent successfully',
        ]);

        $sentCode = null;
        Mail::assertQueued(VerfifyCodeRegister::class, function (VerfifyCodeRegister $mail) use ($user, &$sentCode): bool {
            $sentCode = $mail->code;

            return $mail->hasTo($user->email);
        });

        $this->assertNotNull($sentCode);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $sentCode);

        $user->refresh();
        $this->assertNotNull($user->code_verify_email);
        $this->assertTrue(Hash::check($sentCode, $user->code_verify_email));
        $this->assertTrue($user->expired_verify_email->isFuture());
        $this->assertEqualsWithDelta(
            now()->addMinutes(10)->timestamp,
            $user->expired_verify_email->timestamp,
            2,
        );

        $this->postJson('/api/verify-mail', [
            'email' => $user->email,
            'code' => $sentCode,
        ])->assertOk()->assertExactJson([
            'message' => 'email verified successfully',
        ]);
    }

    public function test_it_returns_not_found_when_the_email_does_not_exist(): void
    {
        $response = $this->postJson('/api/resend-mail', [
            'email' => 'missing@example.com',
        ]);

        $response->assertNotFound()->assertExactJson([
            'message' => 'email not found',
        ]);
    }

    public function test_it_does_not_resend_a_code_for_a_verified_email(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/resend-mail', [
            'email' => $user->email,
        ]);

        $response->assertStatus(400)->assertExactJson([
            'message' => 'email already verified',
        ]);
        Mail::assertNothingQueued();
    }

    public function test_it_rejects_an_invalid_email(): void
    {
        $response = $this->postJson('/api/resend-mail', [
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_it_requires_an_email(): void
    {
        $response = $this->postJson('/api/resend-mail', []);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_it_limits_repeated_resend_requests_from_the_same_ip(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/resend-mail', ['email' => $user->email])->assertOk();
        }

        $this->postJson('/api/resend-mail', ['email' => $user->email])->assertTooManyRequests();
    }
}