<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Password reset link. The frontend resets via POST /api/v1/auth/reset-password. */
class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset your password — '.config('app.name'),
            to: [$this->user->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                // Frontends consume the token through the API; the URL below
                // is a convenience deep-link for email clients.
                'resetUrl' => rtrim((string) config('app.url'), '/')
                    .'/reset-password?token='.$this->token.'&email='.urlencode($this->user->email),
            ],
        );
    }
}
