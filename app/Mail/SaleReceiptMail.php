<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** HTML receipt for a completed sale (queued via SaleCompleted listener). */
class SaleReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $receipt  Shape produced by SaleService::receiptData()
     */
    public function __construct(
        public readonly Sale $sale,
        public readonly array $receipt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Receipt %s — %s', $this->sale->reference_number, config('app.name')),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sale-receipt');
    }
}
