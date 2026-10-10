<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Alert to the business owner when a product drops below min_stock. */
class LowStockAlertMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $owner,
        public readonly Product $product,
        public readonly float $currentStock,
        public readonly float $minStock,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Low stock: %s (%s left)', $this->product->name, number_format($this->currentStock, 2)),
            to: [$this->owner->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.low-stock-alert');
    }
}
