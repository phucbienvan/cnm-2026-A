<?php

namespace Tests\Feature\Auth;

use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_email_is_required_and_valid(): void
    {
        $response = $this->postJson('/api/resend-mail', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_it_returns_404_if_email_not_found(): void
    {
        $response = $this->postJson('/api/resend-mail', [
            'email' => 'notfound@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'email not found',
            ]);
    }

    public function test_it_returns_400_if_email_already_verified(): void
    {
        $user = User::create([
            'name' => 'Verified User',
            'email' => 'verified@example.com',
            'password' => 'password123',
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

    public function test_it_resends_verification_code_successfully(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Unverified User',
            'email' => 'unverified@example.com',
            'password' => 'password123',
            'email_verified_at' => null,
            'code_verify_email' => Hash::make('111111'),
            'expired_verify_email' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/resend-mail', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'verification code resent successfully',
            ]);

        $user->refresh();
        $this->assertNotNull($user->code_verify_email);
        $this->assertNotNull($user->expired_verify_email);
        $this->assertTrue($user->expired_verify_email->isFuture());

        Mail::assertQueued(VerfifyCodeRegister::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }
}
