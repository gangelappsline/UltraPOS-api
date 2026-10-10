<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Simple subscription invoice/receipt (sent on plan upgrades). */
class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $owner,
        public readonly Subscription $subscription,
        public readonly string $invoiceNumber,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice '.$this->invoiceNumber.' — '.config('app.name'),
            to: [$this->owner->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice');
    }
}
