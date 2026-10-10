<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Notifications\SaleReceiptNotification;
use App\Services\SaleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Queued: emails the HTML receipt to the customer (when requested at the
 * POS) and stores an in-app notification for the seller.
 */
class SendSaleReceipt implements ShouldQueue
{
    public function __construct(private readonly SaleService $sales)
    {
    }

    public function handle(SaleCompleted $event): void
    {
        if ($event->sendReceipt) {
            try {
                $this->sales->sendReceipt($event->sale, $event->receiptEmail);
            } catch (\Throwable $e) {
                // Receipt delivery must never fail the sale.
                Log::warning('Sale receipt could not be queued: '.$e->getMessage(), [
                    'sale_id' => $event->sale->id,
                ]);
            }
        }

        $event->seller->notify(
            new SaleReceiptNotification($this->sales->receiptData($event->sale))
        );
    }
}
