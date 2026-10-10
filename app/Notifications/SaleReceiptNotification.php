<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * In-app (database) copy of the sale receipt for the seller/owner.
 * The customer receives the HTML receipt via SaleReceiptMail.
 */
class SaleReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $receipt
     */
    public function __construct(public readonly array $receipt)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'sale_receipt',
            'reference_number' => $this->receipt['sale']['reference_number'] ?? null,
            'total' => $this->receipt['sale']['total'] ?? null,
            'status' => $this->receipt['sale']['status'] ?? null,
            'message' => sprintf(
                'Sale %s completed (total %s).',
                $this->receipt['sale']['reference_number'] ?? '?',
                number_format((float) ($this->receipt['sale']['total'] ?? 0), 2),
            ),
        ];
    }
}
