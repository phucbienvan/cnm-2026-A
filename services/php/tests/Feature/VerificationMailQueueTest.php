<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class VerificationMailQueueTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database', 'mail.default' => 'array']);
    }

    public function test_resend_is_queued_after_commit_and_can_be_processed_by_a_worker(): void
    {
        $user = User::factory()->unverified()->create();
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        DB::transaction(function () use ($user): void {
            $this->postJson('/api/resend-mail', ['email' => $user->email])->assertOk();
            $this->assertDatabaseCount('jobs', 0);
        });

        $this->assertDatabaseCount('jobs', 1);
        $this->assertCount(0, $transport->messages());
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1])
            ->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $message = $transport->messages()->sole()->getOriginalMessage();
        $this->assertSame($user->email, $message->getTo()[0]->getAddress());
        $this->assertSame('Email verification code', $message->getSubject());
        $this->assertStringContainsString('10 minutes', $message->getHtmlBody());
        $this->assertSame(1, preg_match('/This is code: ([0-9]{6})/', $message->getHtmlBody(), $matches));
        $this->assertTrue(Hash::check($matches[1], $user->fresh()->code_verify_email));
    }

    public function test_a_rolled_back_resend_does_not_queue_an_email_or_replace_the_code(): void
    {
        $user = User::factory()->unverified()->create([
            'code_verify_email' => Hash::make('123456'),
            'expired_verify_email' => now()->addMinutes(5),
        ]);
        $original = $user->refresh()->getAttributes();

        try {
            DB::transaction(function () use ($user): void {
                $this->postJson('/api/resend-mail', ['email' => $user->email])->assertOk();

                throw new RuntimeException('Abort transaction');
            });
            $this->fail('The transaction should have been aborted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Abort transaction', $exception->getMessage());
        }

        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('jobs', 0);
    }
}
