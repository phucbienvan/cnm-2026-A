<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Http\Requests\Auth\LoginRequest;
use App\Mail\VerfifyCodeRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\PendingMail;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    use DatabaseMigrations;

    private MailManager $mailManager;

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
            'queue.connections.database.connection' => 'sqlite',
            'queue.failed.database' => 'sqlite',
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
        $this->mailManager = Mail::getFacadeRoot();
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

    public function test_queue_failure_after_commit_returns_safe_error_and_keeps_the_new_code(): void
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
            ->andReturnUsing(function (VerfifyCodeRegister $mail) use ($user): void {
                $this->assertSame(0, DB::transactionLevel());
                $this->assertFalse(DB::connection()->getPdo()->inTransaction());
                $this->assertTrue(Hash::check($mail->code, $user->fresh()->code_verify_email));
                throw new RuntimeException('Sensitive transport details');
            });
        Log::shouldReceive('error')->once()->with('Verification code saved, but verification email could not be queued.');

        $this->resend($user)->assertStatus(503)->assertHeader('Retry-After', '60')->assertExactJson([
            'message' => 'Verification code saved, but email could not be queued. Please retry after 60 seconds.',
        ]);

        $user->refresh();
        $this->assertNotSame($hash, $user->code_verify_email);
        $this->assertFalse($expiry->equalTo($user->expired_verify_email));
        $this->assertTrue($user->expired_verify_email->equalTo(now()->addMinutes(10)));
        $this->resend($user)->assertStatus(429);
    }

    public function test_database_failure_rolls_back_code_without_enqueuing(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->addMinutes(5),
        ]);
        $hash = $user->code_verify_email;
        $expiry = $user->expired_verify_email;
        User::updated(function (): void {
            throw new RuntimeException('Database failure');
        });
        Log::shouldReceive('error')->once()->with('Unable to save verification code.');

        $this->resend($user)->assertStatus(503)->assertExactJson([
            'message' => 'Unable to save verification code. Please try again later.',
        ]);

        $this->assertSame($hash, $user->fresh()->code_verify_email);
        $this->assertTrue($expiry->equalTo($user->fresh()->expired_verify_email));
        Mail::assertNothingOutgoing();
    }

    public function test_random_collision_is_retried_before_replacing_the_code(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->addMinutes(5),
        ]);
        $controller = Mockery::mock(AuthController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('generateVerificationCode')->twice()->andReturn('123456', '654321');
        $this->app->instance(AuthController::class, $controller);

        $this->resend($user)->assertStatus(202);

        $mail = Mail::queued(VerfifyCodeRegister::class)->sole();
        $this->assertSame('654321', $mail->code);
        $this->assertFalse(Hash::check('123456', $user->fresh()->code_verify_email));
        $this->postJson('/api/verify-mail', ['email' => $user->email, 'code' => '123456'])->assertUnauthorized();
        $this->postJson('/api/verify-mail', ['email' => $user->email, 'code' => '654321'])->assertOk();
    }

    public function test_verification_rejects_a_stale_snapshot_if_resend_replaces_the_code_after_reading(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->addMinutes(5),
        ]);
        $interleaved = false;
        User::retrieved(function (User $snapshot) use (&$interleaved): void {
            if ($interleaved) {
                return;
            }
            $interleaved = true;
            $this->assertSame(1, DB::transactionLevel());
            $input = ['email' => $snapshot->email, 'password' => 'password'];
            $request = new LoginRequest;
            $request->setValidator(Validator::make($input, $request->rules()));
            $this->assertSame(202, app(AuthController::class)->resendMail($request)->getStatusCode());
        });

        $this->postJson('/api/verify-mail', ['email' => $user->email, 'code' => '123456'])
            ->assertUnauthorized()->assertJsonPath('message', 'code is incorrect');
        $this->assertTrue($interleaved);
        $this->assertNull($user->fresh()->email_verified_at);
        $mail = Mail::queued(VerfifyCodeRegister::class)->sole();
        $this->postJson('/api/verify-mail', ['email' => $user->email, 'code' => $mail->code])->assertOk();
    }

    public function test_mail_is_not_enqueued_until_the_outer_transaction_commits(): void
    {
        $user = User::factory()->unverified()->create();
        DB::beginTransaction();
        try {
            $this->resend($user)->assertStatus(202);
            Mail::assertNothingOutgoing();
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        Mail::assertQueuedCount(1);
    }

    public function test_rolling_back_the_outer_transaction_discards_the_pending_mail(): void
    {
        $user = User::factory()->unverified()->create();
        DB::beginTransaction();
        try {
            $this->resend($user)->assertStatus(202);
            Mail::assertNothingOutgoing();
        } finally {
            DB::rollBack();
        }
        $this->assertNull($user->fresh()->code_verify_email);
        Mail::assertNothingOutgoing();
    }

    public function test_obsolete_job_is_skipped_and_current_job_sends_the_current_code(): void
    {
        $user = User::factory()->unverified()->create();
        $this->resend($user)->assertStatus(202);
        $oldMail = Mail::queued(VerfifyCodeRegister::class)->sole();
        $this->travel(61)->seconds();
        $this->resend($user)->assertStatus(202);
        $currentMail = Mail::queued(VerfifyCodeRegister::class)->last();

        (new SendQueuedMailable($oldMail))->handle($this->mailManager);
        $this->assertCount(0, $this->mailManager->mailer('array')->getSymfonyTransport()->messages());
        (new SendQueuedMailable($currentMail))->handle($this->mailManager);
        $messages = $this->mailManager->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString($currentMail->code, $messages->sole()->getOriginalMessage()->getHtmlBody());
        $this->assertTrue(Hash::check($currentMail->code, $user->fresh()->code_verify_email));
    }

    public function test_verification_prevents_resend_and_skips_pending_mail(): void
    {
        $user = User::factory()->unverified()->create();
        $this->resend($user)->assertStatus(202);
        $mail = Mail::queued(VerfifyCodeRegister::class)->sole();
        $this->postJson('/api/verify-mail', ['email' => $user->email, 'code' => $mail->code])->assertOk();
        $this->resend($user)->assertStatus(409);
        $this->postJson('/api/verify-mail', ['email' => $user->email, 'code' => $mail->code])->assertStatus(409);

        (new SendQueuedMailable($mail))->handle($this->mailManager);
        $this->assertCount(0, $this->mailManager->mailer('array')->getSymfonyTransport()->messages());
    }

    #[DataProvider('deliveryAges')]
    public function test_worker_checks_expiry_at_the_ten_minute_boundary(int $seconds, int $expectedMessages): void
    {
        $user = User::factory()->unverified()->create();
        $this->resend($user)->assertStatus(202);
        $mail = Mail::queued(VerfifyCodeRegister::class)->sole();
        $this->travel($seconds)->seconds();

        (new SendQueuedMailable($mail))->handle($this->mailManager);
        $this->assertCount($expectedMessages, $this->mailManager->mailer('array')->getSymfonyTransport()->messages());
    }

    public static function deliveryAges(): array
    {
        return ['before expiry' => [599, 1], 'at expiry' => [600, 0], 'after expiry' => [601, 0]];
    }

    public function test_code_can_be_verified_one_second_before_expiry(): void
    {
        $user = User::factory()->unverified()->create();
        $this->resend($user)->assertStatus(202);
        $mail = Mail::queued(VerfifyCodeRegister::class)->sole();
        $this->travel(599)->seconds();
        $this->postJson('/api/verify-mail', ['email' => $user->email, 'code' => $mail->code])->assertOk();
    }

    public function test_registration_mail_sends_if_current_and_is_skipped_after_resend(): void
    {
        $this->postJson('/api/register', [
            'name' => 'New user', 'email' => 'new@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertOk();
        $user = User::where('email', 'new@example.com')->sole();
        $registrationMail = Mail::queued(VerfifyCodeRegister::class)->sole();
        $this->assertTrue($registrationMail->afterCommit);
        (new SendQueuedMailable($registrationMail))->handle($this->mailManager);
        $transport = $this->mailManager->mailer('array')->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());
        $transport->flush();

        $this->travel(61)->seconds();
        $this->resend($user)->assertStatus(202);
        (new SendQueuedMailable($registrationMail))->handle($this->mailManager);
        $this->assertCount(0, $transport->messages());
    }

    public function test_database_queue_dispatches_after_commit_and_worker_delivers_matching_code(): void
    {
        Mail::swap($this->mailManager);
        config(['queue.default' => 'database']);
        $user = User::factory()->unverified()->create();
        $inserted = false;
        DB::listen(function ($query) use (&$inserted): void {
            if (str_starts_with($query->sql, 'insert into "jobs"')) {
                $inserted = true;
                $this->assertSame(0, DB::transactionLevel());
                $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            }
        });

        $this->resend($user)->assertStatus(202);
        $this->assertTrue($inserted);
        $payload = json_decode(DB::table('jobs')->sole()->payload, true);
        $command = unserialize($payload['data']['command']);
        $this->assertTrue(Hash::check($command->mailable->code, $user->fresh()->code_verify_email));
        $job = Queue::connection('database')->pop();
        $this->assertNotNull($job);
        $job->fire();
        $messages = $this->mailManager->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString($command->mailable->code, $messages->sole()->getOriginalMessage()->getHtmlBody());
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_worker_send_failure_does_not_roll_back_a_committed_code(): void
    {
        $user = User::factory()->unverified()->create();
        $this->resend($user)->assertStatus(202);
        $hash = $user->fresh()->code_verify_email;
        $mail = Mail::queued(VerfifyCodeRegister::class)->sole();
        Event::listen(MessageSending::class, function (): void {
            throw new RuntimeException('Simulated transport failure');
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Simulated transport failure');

        try {
            (new SendQueuedMailable($mail))->handle($this->mailManager);
        } finally {
            $this->assertSame($hash, $user->fresh()->code_verify_email);
            $this->assertNull($user->fresh()->email_verified_at);
            $this->assertCount(0, $this->mailManager->mailer('array')->getSymfonyTransport()->messages());
        }
    }

    public function test_database_queue_failure_keeps_the_committed_code_and_returns_retryable_error(): void
    {
        Mail::swap($this->mailManager);
        config([
            'queue.default' => 'database',
            'queue.connections.database.table' => 'missing_verification_queue',
        ]);
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->addMinutes(5),
        ]);
        Log::shouldReceive('error')->once()->with('Verification code saved, but verification email could not be queued.');

        $this->resend($user)->assertStatus(503)->assertHeader('Retry-After', '60')->assertExactJson([
            'message' => 'Verification code saved, but email could not be queued. Please retry after 60 seconds.',
        ]);

        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(Hash::check('123456', $user->fresh()->code_verify_email));
        $this->assertTrue($user->fresh()->expired_verify_email->equalTo(now()->addMinutes(10)));
        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(0, $this->mailManager->mailer('array')->getSymfonyTransport()->messages());
        $this->resend($user)->assertStatus(429);
    }

    private function resend(User $user): TestResponse
    {
        return $this->postJson('/api/resend-mail', [
            'email' => $user->email,
            'password' => 'password',
        ]);
    }
}
