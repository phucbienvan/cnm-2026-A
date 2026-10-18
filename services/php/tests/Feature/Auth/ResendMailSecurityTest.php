<?php

namespace Tests\Feature\Auth;

use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendMailSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_does_not_reveal_whether_an_email_is_registered(): void
    {
        Mail::fake();
        User::factory()->create([
            'email' => 'verified@example.com',
        ]);

        $unknown = $this->postJson('/api/resend-mail', [
            'email' => 'unknown@example.com',
        ]);

        $verified = $this->postJson('/api/resend-mail', [
            'email' => 'verified@example.com',
        ]);

        $unknown->assertStatus(202)->assertJson([
            'message' => 'If the email is eligible, a verification code has been sent.',
        ]);
        $verified->assertStatus(202)->assertJson([
            'message' => 'If the email is eligible, a verification code has been sent.',
        ]);
        Mail::assertNotQueued(VerfifyCodeRegister::class);
    }

    public function test_resend_updates_the_code_and_queues_mail_for_unverified_user(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/resend-mail', [
            'email' => $user->email,
        ]);

        $response->assertStatus(202);
        $user->refresh();

        $this->assertNotNull($user->code_verify_email);
        $this->assertTrue($user->expired_verify_email->isFuture());
        Mail::assertQueued(VerfifyCodeRegister::class, 1);
    }

    public function test_resend_is_rate_limited_per_email_and_ip(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/resend-mail', [
                'email' => $user->email,
            ])->assertStatus(202);
        }

        $this->postJson('/api/resend-mail', [
            'email' => $user->email,
        ])->assertStatus(429);
    }

    public function test_verification_is_rate_limited_per_email_and_ip(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->addMinutes(10),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/verify-mail', [
                'email' => $user->email,
                'code' => '000000',
            ])->assertStatus(401);
        }

        $this->postJson('/api/verify-mail', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertStatus(429);
    }
}