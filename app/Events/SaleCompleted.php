<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a sale is committed. Queued listeners handle side effects
 * (receipt email, notifications) without blocking the POS response.
 */
class SaleCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Sale $sale,
        public readonly User $seller,
        public readonly ?string $receiptEmail = null,
        public readonly bool $sendReceipt = false,
    ) {
    }
}
