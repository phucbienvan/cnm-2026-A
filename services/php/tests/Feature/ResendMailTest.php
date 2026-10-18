<?php

namespace Tests\Feature;

use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->freezeSecond();
    }

    public function test_resending_rotates_the_code_and_resets_its_expiry(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->addMinute(),
        ]);

        $this->postJson('/api/resend-mail', ['email' => $user->email])
            ->assertOk()
            ->assertExactJson(['message' => 'Verification email queued successfully']);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->expired_verify_email->equalTo(now()->addMinutes(10)));
        $this->assertFalse(Hash::check('123456', $user->code_verify_email));
        Mail::assertQueued(VerfifyCodeRegister::class, function (VerfifyCodeRegister $mail) use ($user): bool {
            $this->assertMatchesRegularExpression('/^[1-9][0-9]{5}$/', $mail->code);
            $this->assertTrue(Hash::check($mail->code, $user->code_verify_email));
            $this->assertNotSame($mail->code, $user->code_verify_email);
            $this->assertSame($user->name, $mail->username);
            $this->assertTrue($mail->afterCommit);

            return $mail->hasTo($user->email);
        });
        Mail::assertQueuedCount(1);
    }

    public function test_an_expired_code_can_be_resent(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->subMinute(),
        ]);

        $this->postJson('/api/resend-mail', ['email' => $user->email])->assertOk();

        $this->assertTrue($user->fresh()->expired_verify_email->equalTo(now()->addMinutes(10)));
        Mail::assertQueuedCount(1);
    }

    public function test_an_unverified_account_without_a_code_can_request_one(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/resend-mail', ['email' => $user->email])->assertOk();

        $this->assertNotNull($user->fresh()->code_verify_email);
        Mail::assertQueuedCount(1);
    }

    #[DataProvider('invalidEmails')]
    public function test_resending_validates_the_email(mixed $email): void
    {
        $this->postJson('/api/resend-mail', ['email' => $email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingQueued();
    }

    public static function invalidEmails(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'invalid address' => ['not-an-email'],
            'array' => [['user@example.com']],
            'number' => [123456],
            'too long' => [str_repeat('a', 250).'@example.com'],
        ];
    }

    public function test_resending_requires_an_email(): void
    {
        $this->postJson('/api/resend-mail')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingQueued();
    }

    public function test_an_unknown_email_is_rejected(): void
    {
        $this->postJson('/api/resend-mail', ['email' => 'missing@example.com'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'email not found']);

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_verified_account_cannot_request_another_code(): void
    {
        $user = User::factory()->create();
        $original = $user->refresh()->getAttributes();

        $this->postJson('/api/resend-mail', ['email' => $user->email])
            ->assertConflict()
            ->assertExactJson(['message' => 'email already verified']);

        $this->assertSame($original, $user->fresh()->getAttributes());
        Mail::assertNothingQueued();
    }

    public function test_the_same_email_is_rate_limited_across_ip_addresses_and_case(): void
    {
        $user = User::factory()->unverified()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
            ->postJson('/api/resend-mail', ['email' => $user->email])->assertOk();
        $original = $user->fresh()->getAttributes();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])
            ->postJson('/api/resend-mail', ['email' => strtoupper($user->email)])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->assertSame($original, $user->fresh()->getAttributes());
        Mail::assertQueuedCount(1);
    }

    public function test_different_emails_on_one_ip_are_limited_to_five_requests_per_minute(): void
    {
        $users = User::factory()->unverified()->count(6)->create();

        foreach ($users->take(5) as $user) {
            $this->postJson('/api/resend-mail', ['email' => $user->email])->assertOk();
        }

        $this->postJson('/api/resend-mail', ['email' => $users->last()->email])
            ->assertTooManyRequests();

        $this->assertNull($users->last()->fresh()->code_verify_email);
        Mail::assertQueuedCount(5);
    }

    public function test_resending_is_allowed_again_after_the_cooldown(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/resend-mail', ['email' => $user->email])->assertOk();
        $oldCode = Mail::queued(VerfifyCodeRegister::class)->sole()->code;
        $this->travel(61)->seconds();

        $this->postJson('/api/resend-mail', ['email' => $user->email])->assertOk();

        $this->assertFalse(Hash::check($oldCode, $user->fresh()->code_verify_email));
        $this->assertTrue($user->fresh()->expired_verify_email->equalTo(now()->addMinutes(10)));
        Mail::assertQueuedCount(2);
    }

    public function test_a_mail_preparation_failure_rolls_back_the_code_change(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->addMinutes(5),
        ]);
        $original = $user->refresh()->getAttributes();
        Mail::shouldReceive('to')->once()->with($user->email)->andThrow(new RuntimeException('Mail unavailable'));

        $this->postJson('/api/resend-mail', ['email' => $user->email])->assertStatus(500);

        $this->assertSame($original, $user->fresh()->getAttributes());
    }

    public function test_registration_resend_verification_and_login_work_together(): void
    {
        $email = 'new-user@example.com';
        $this->postJson('/api/register', [
            'name' => 'New user',
            'email' => $email,
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
        ])->assertOk();
        $oldCode = Mail::queued(VerfifyCodeRegister::class)->sole()->code;

        $this->postJson('/api/login', ['email' => $email, 'password' => 'test-password'])
            ->assertUnauthorized();
        $this->postJson('/api/resend-mail', ['email' => $email])->assertOk();
        $newCode = Mail::queued(VerfifyCodeRegister::class)->last()->code;
        $this->assertNotSame($oldCode, $newCode);

        $this->postJson('/api/verify-mail', ['email' => $email, 'code' => $oldCode])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'code is incorrect']);
        $this->postJson('/api/verify-mail', ['email' => $email, 'code' => (int) $newCode])
            ->assertOk()
            ->assertExactJson(['message' => 'email verified successfully']);

        $user = User::where('email', $email)->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->code_verify_email);
        $this->assertNull($user->expired_verify_email);
        $this->postJson('/api/verify-mail', ['email' => $email, 'code' => $newCode])
            ->assertConflict();
        $this->travel(61)->seconds();
        $this->postJson('/api/resend-mail', ['email' => $email])->assertConflict();
        $token = $this->postJson('/api/login', ['email' => $email, 'password' => 'test-password'])
            ->assertOk()->json('access_token');
        $this->assertIsString($token);
        $this->withToken($token)->getJson('/api/users')->assertOk()->assertJsonPath('email', $email);
    }

    public function test_a_resent_code_is_invalid_at_its_expiry_time(): void
    {
        $user = User::factory()->unverified()->create();
        $this->postJson('/api/resend-mail', ['email' => $user->email])->assertOk();
        $code = Mail::queued(VerfifyCodeRegister::class)->sole()->code;
        $this->travel(10)->minutes();

        $this->postJson('/api/verify-mail', ['email' => $user->email, 'code' => $code])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'code is expired']);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[DataProvider('incompleteVerificationData')]
    public function test_incomplete_verification_data_cannot_verify_an_account(?string $code, ?string $expiry): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => $code === null ? null : Hash::make($code),
            'expired_verify_email' => $expiry,
        ]);

        $this->postJson('/api/verify-mail', ['email' => $user->email, 'code' => '123456'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'code is expired']);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public static function incompleteVerificationData(): array
    {
        return [
            'both missing' => [null, null],
            'expiry missing' => ['123456', null],
            'code missing' => [null, '2099-01-01 00:00:00'],
        ];
    }

    #[DataProvider('invalidCodes')]
    public function test_verification_requires_exactly_six_digits(mixed $code): void
    {
        $this->postJson('/api/verify-mail', ['email' => 'user@example.com', 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public static function invalidCodes(): array
    {
        return [
            'missing' => [null],
            'too short' => ['12345'],
            'too long' => ['1234567'],
            'letters' => ['abcdef'],
            'array' => [['123456']],
        ];
    }

    public function test_an_unknown_email_cannot_be_verified(): void
    {
        $this->postJson('/api/verify-mail', ['email' => 'missing@example.com', 'code' => '123456'])
            ->assertNotFound();
    }

    public function test_verification_attempts_are_limited_across_ip_addresses(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->addMinutes(10),
        ]);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->postJson('/api/verify-mail', ['email' => $user->email, 'code' => '000000'])
                ->assertUnauthorized();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.7'])
            ->postJson('/api/verify-mail', ['email' => $user->email, 'code' => '123456'])
            ->assertTooManyRequests();
        $this->assertNull($user->fresh()->email_verified_at);

        $this->travel(61)->seconds();
        $this->postJson('/api/verify-mail', ['email' => $user->email, 'code' => '123456'])->assertOk();
    }

    public function test_the_user_api_does_not_expose_verification_fields(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->addMinutes(10),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/users')->assertOk()
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('code_verify_email')
            ->assertJsonMissingPath('expired_verify_email');
    }
}
