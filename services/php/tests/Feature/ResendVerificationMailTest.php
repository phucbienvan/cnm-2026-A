<?php

namespace Tests\Feature;

use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendVerificationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_can_request_a_new_verification_code(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('111111'),
            'expired_verify_email' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/resend-mail', [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'verification email resent successfully',
            ]);

        $user->refresh();

        $this->assertTrue($user->expired_verify_email->isAfter(now()->addMinutes(9)));
        $this->assertTrue($user->expired_verify_email->isBefore(now()->addMinutes(11)));

        Mail::assertQueued(VerfifyCodeRegister::class, function (VerfifyCodeRegister $mail) use ($user) {
            return $mail->hasTo($user->email)
                && Hash::check($mail->code, $user->code_verify_email);
        });
    }

    public function test_resend_returns_not_found_for_an_unknown_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/resend-mail', [
            'email' => 'missing@example.com',
        ]);

        $response->assertNotFound()
            ->assertJson([
                'message' => 'email not found',
            ]);

        Mail::assertNothingQueued();
    }

    public function test_resend_is_rejected_when_email_is_already_verified(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/resend-mail', [
            'email' => $user->email,
        ]);

        $response->assertConflict()
            ->assertJson([
                'message' => 'email already verified',
            ]);

        Mail::assertNothingQueued();
    }

    public function test_resend_requires_a_valid_email(): void
    {
        $response = $this->postJson('/api/resend-mail', [
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
