<?php

namespace Tests\Feature;

use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_resend_verification_email_successfully(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/resend-mail', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'verification code resent successfully',
            ]);

        Mail::assertQueued(VerfifyCodeRegister::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $user->refresh();
        $this->assertNotNull($user->code_verify_email);
        $this->assertNotNull($user->expired_verify_email);
    }

    public function test_cannot_resend_mail_if_user_not_found(): void
    {
        $response = $this->postJson('/api/resend-mail', [
            'email' => 'notfound@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'email not found',
            ]);
    }

    public function test_cannot_resend_mail_if_already_verified(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/resend-mail', [
            'email' => $user->email,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'email already verified',
            ]);
    }

    public function test_resend_mail_requires_valid_email(): void
    {
        $response = $this->postJson('/api/resend-mail', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_resend_mail_requires_email_field(): void
    {
        $response = $this->postJson('/api/resend-mail', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}