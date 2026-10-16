<?php

namespace Tests\Feature;

use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_and_stores_a_new_code_for_an_unverified_user(): void
    {
        Mail::fake();
        $oldCode = Hash::make('000000');
        $user = User::factory()->unverified()->create([
            'code_verify_email' => $oldCode,
            'expired_verify_email' => now()->subMinute(),
        ]);

        $this->postJson('/api/resend-email', ['email' => $user->email])
            ->assertStatus(202);

        $user->refresh();
        $this->assertNotSame($oldCode, $user->code_verify_email);
        $this->assertTrue($user->expired_verify_email->greaterThan(now()->addMinutes(9)));
        $newCode = null;
        Mail::assertSent(VerfifyCodeRegister::class, function (VerfifyCodeRegister $mail) use ($user, &$newCode): bool {
            $newCode = $mail->code;

            return $mail->hasTo($user->email)
                && Hash::check($mail->code, $user->code_verify_email);
        });

        $this->postJson('/api/verify-mail', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertStatus(401);

        $this->postJson('/api/verify-mail', [
            'email' => $user->email,
            'code' => $newCode,
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_it_does_not_send_to_missing_or_verified_email(): void
    {
        Mail::fake();
        $verifiedUser = User::factory()->create();

        $missingResponse = $this->postJson('/api/resend-email', ['email' => 'missing@example.com']);
        $verifiedResponse = $this->postJson('/api/resend-email', ['email' => $verifiedUser->email]);

        $missingResponse->assertStatus(202);
        $verifiedResponse->assertStatus(202)
            ->assertExactJson($missingResponse->json());
        Mail::assertNothingSent();
    }

    public function test_it_limits_repeated_requests_for_the_same_email(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/resend-email', ['email' => $user->email])->assertStatus(202);
        $this->postJson('/api/resend-email', ['email' => $user->email])->assertStatus(429);

        Mail::assertSentCount(1);
    }

    public function test_it_rejects_an_invalid_email(): void
    {
        Mail::fake();

        $this->postJson('/api/resend-email', ['email' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
    }

    public function test_verification_rejects_a_missing_code(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/verify-mail', [
            'email' => $user->email,
            'code' => '123456',
        ])->assertStatus(401)
            ->assertJsonPath('message', 'code is expired');
    }
}
