<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Purchase $purchase,
        public readonly User $receivedBy,
    ) {
    }
}
