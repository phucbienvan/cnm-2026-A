<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\SentMessage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;

class VerfifyCodeRegister extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $username,
    ) {
        $this->afterCommit();
    }

    /**
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer): ?SentMessage
    {
        if (count($this->to) !== 1) {
            return null;
        }

        $user = User::where('email', $this->to[0]['address'])->first();

        if (! $user || $user->email_verified_at
            || ! $user->expired_verify_email || $user->expired_verify_email->lessThanOrEqualTo(now())
            || ! $user->code_verify_email || ! Hash::check($this->code, $user->code_verify_email)) {
            return null;
        }

        return parent::send($mailer);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verfify Code Register',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-code-register',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
