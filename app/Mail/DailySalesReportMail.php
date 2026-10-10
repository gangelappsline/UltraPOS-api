<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Business;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Daily sales digest sent (queued) to the business owner at 23:55. */
class DailySalesReportMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $summary  Shape produced by SaleService::dailySummary()
     */
    public function __construct(
        public readonly User $owner,
        public readonly Business $business,
        public readonly array $summary,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Daily sales report %s — %s', $this->summary['date'] ?? now()->toDateString(), $this->business->name),
            to: [$this->owner->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-sales-report');
    }
}
