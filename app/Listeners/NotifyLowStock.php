<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LowStockDetected;
use App\Mail\LowStockAlertMail;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/** Queued: alerts the business owner by email + in-app notification. */
class NotifyLowStock implements ShouldQueue
{
    public function handle(LowStockDetected $event): void
    {
        $business = $event->product->business;
        $owner = $business?->owner;

        if ($owner === null) {
            return;
        }

        Mail::to($owner->email)->queue(new LowStockAlertMail(
            $owner,
            $event->product,
            $event->currentStock,
            $event->minStock,
        ));

        $owner->notify(LowStockNotification::fromProduct($event->product));
    }
}
