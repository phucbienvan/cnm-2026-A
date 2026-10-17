<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_resend_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/email/verification-notification');

        $response->assertOk()
            ->assertJson(['message' => 'Verification email sent.']);

        Notification::assertSentTo($user, VerifyEmail::class);

        $verificationMail = (new VerifyEmail)->toMail($user);

        $this->assertStringStartsWith(
            route('verification.verify', [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]),
            $verificationMail->actionUrl,
        );
    }

    public function test_guest_cannot_resend_verification_email(): void
    {
        $this->postJson('/api/email/verification-notification')
            ->assertUnauthorized();
    }

    public function test_verified_user_does_not_receive_another_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJson(['message' => 'Email address is already verified.']);

        Notification::assertNothingSent();
    }

    public function test_valid_signed_verification_link_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJson(['message' => 'Email address verified.']);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
