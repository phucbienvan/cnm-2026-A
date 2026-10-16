<?php

namespace Tests\Feature;

use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'url' => null,
                    'database' => ':memory:',
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                ],
            ],
            'cache.default' => 'array',
            'cache.limiter' => 'array',
            'queue.default' => 'sync',
            'mail.default' => 'array',
            'session.driver' => 'array',
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        Mail::fake();
    }

    public function test_resends_the_existing_mailable_with_a_hashed_code_and_ten_minute_expiry(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/resend-mail', [
            'email' => $user->email,
            'password' => 'password',
            'to' => 'other@example.com',
        ])->assertStatus(202)->assertExactJson([
            'message' => 'Verification email queued successfully',
        ]);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->expired_verify_email->equalTo(now()->addMinutes(10)));
        Mail::assertQueued(VerfifyCodeRegister::class, function (VerfifyCodeRegister $mail) use ($user): bool {
            $this->assertMatchesRegularExpression('/^[1-9][0-9]{5}$/', $mail->code);
            $this->assertNotSame($mail->code, $user->code_verify_email);
            $this->assertTrue(Hash::check($mail->code, $user->code_verify_email));
            $this->assertSame($user->name, $mail->username);
            $this->assertCount(1, $mail->to);
            $mail->assertSeeInHtml($mail->code);

            return $mail->hasTo($user->email) && ! $mail->hasTo('other@example.com');
        });
        Mail::assertQueuedCount(1);
        Mail::assertNothingSent();
    }

    public function test_replacement_code_works_with_the_existing_verification_endpoint(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('000000'),
            'expired_verify_email' => now()->subMinute(),
        ]);

        $this->resend($user)->assertStatus(202);
        $mail = Mail::queued(VerfifyCodeRegister::class)->sole();

        $this->postJson('/api/verify-mail', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertStatus(401)->assertJsonPath('message', 'code is incorrect');

        $this->postJson('/api/verify-mail', [
            'email' => $user->email,
            'code' => $mail->code,
        ])->assertOk()->assertJsonPath('message', 'email verified successfully');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->code_verify_email);
        $this->assertNull($user->expired_verify_email);
    }

    public function test_replacement_code_expires_after_ten_minutes(): void
    {
        $user = User::factory()->unverified()->create();
        $this->resend($user)->assertStatus(202);
        $mail = Mail::queued(VerfifyCodeRegister::class)->sole();

        $this->travel(10)->minutes();
        $this->travel(1)->seconds();

        $this->postJson('/api/verify-mail', [
            'email' => $user->email,
            'code' => $mail->code,
        ])->assertStatus(401)->assertJsonPath('message', 'code is expired');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[DataProvider('invalidRequests')]
    public function test_rejects_invalid_credentials_input(array $payload, array $fields): void
    {
        $this->postJson('/api/resend-mail', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($fields);
        Mail::assertNothingOutgoing();
    }

    public static function invalidRequests(): array
    {
        return [
            'missing credentials' => [[], ['email', 'password']],
            'invalid email' => [['email' => 'invalid', 'password' => 'password'], ['email']],
            'non-string password' => [['email' => 'user@example.com', 'password' => []], ['password']],
            'missing password' => [['email' => 'user@example.com'], ['password']],
        ];
    }

    public function test_unknown_account_and_wrong_password_return_the_same_unauthorized_response(): void
    {
        $user = User::factory()->unverified()->create();

        foreach ([$user->email, 'missing@example.com'] as $email) {
            $this->postJson('/api/resend-mail', [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertUnauthorized()->assertExactJson([
                'message' => 'email or password incorrect',
            ]);
        }

        $this->assertNull($user->fresh()->code_verify_email);
        Mail::assertNothingOutgoing();
    }

    public function test_bearer_token_does_not_allow_resending_for_another_account(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->unverified()->create(['password' => 'different-password']);
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)->resend($target)->assertUnauthorized();
        $this->assertNull($target->fresh()->code_verify_email);
        Mail::assertNothingOutgoing();
    }

    public function test_verified_account_is_rejected_only_after_checking_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/resend-mail', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
        $this->resend($user)->assertStatus(409)->assertExactJson([
            'message' => 'email already verified',
        ]);

        $this->assertNull($user->fresh()->code_verify_email);
        Mail::assertNothingOutgoing();
    }

    public function test_account_cooldown_applies_across_ips_and_allows_retry_after_sixty_seconds(): void
    {
        $user = User::factory()->unverified()->create();
        $this->resend($user)->assertStatus(202);
        $hash = $user->fresh()->code_verify_email;

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->resend($user)->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->assertSame($hash, $user->fresh()->code_verify_email);
        Mail::assertQueuedCount(1);

        $this->travel(60)->seconds();
        $this->resend($user)->assertStatus(202);
        Mail::assertQueuedCount(2);
    }

    public function test_recent_registration_code_also_enforces_account_cooldown(): void
    {
        $this->postJson('/api/register', [
            'name' => 'New user',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertOk();
        $user = User::where('email', 'new@example.com')->sole();

        $this->resend($user)->assertStatus(429)->assertHeader('Retry-After', '60');
        Mail::assertQueuedCount(1);
    }

    public function test_ip_limit_blocks_repeated_attempts_and_recovers_after_one_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/resend-mail', [
                'email' => 'missing@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $user = User::factory()->unverified()->create();
        $this->resend($user)->assertStatus(429)->assertHeader('Retry-After', '60');
        Mail::assertNothingOutgoing();

        $this->travel(61)->seconds();
        $this->resend($user)->assertStatus(202);
        Mail::assertQueuedCount(1);
    }

    public function test_queue_failure_returns_safe_error_and_preserves_the_previous_code(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('000000'),
            'expired_verify_email' => now()->addMinutes(5),
        ]);
        $hash = $user->code_verify_email;
        $expiry = $user->expired_verify_email;
        $pendingMail = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->once()->with($user->email)->andReturn($pendingMail);
        $pendingMail->shouldReceive('queue')->once()->with(Mockery::type(VerfifyCodeRegister::class))
            ->andThrow(new RuntimeException('Sensitive transport details'));
        Log::shouldReceive('error')->once()->with('Unable to queue verification email.');

        $this->resend($user)->assertStatus(503)->assertExactJson([
            'message' => 'Unable to queue verification email. Please try again later.',
        ]);

        $user->refresh();
        $this->assertSame($hash, $user->code_verify_email);
        $this->assertTrue($expiry->equalTo($user->expired_verify_email));
    }

    private function resend(User $user): TestResponse
    {
        return $this->postJson('/api/resend-mail', [
            'email' => $user->email,
            'password' => 'password',
        ]);
    }
}
