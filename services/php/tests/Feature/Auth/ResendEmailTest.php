<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResendEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_resend_the_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->postJson('/api/email/resend', [], [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJson(['message' => 'Verification email sent successfully']);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unauthenticated_user_cannot_resend_the_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/email/resend')->assertUnauthorized();

        Notification::assertNothingSent();
    }

    public function test_verified_user_does_not_receive_another_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->postJson('/api/email/resend', [], [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJson(['message' => 'Email already verified']);

        Notification::assertNothingSent();
    }

    public function test_verification_email_link_verifies_the_user(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = (new VerifyEmail)->toMail($user)->actionUrl;

        $this->getJson($verificationUrl)->assertOk()
            ->assertJson(['message' => 'Email verified successfully']);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}