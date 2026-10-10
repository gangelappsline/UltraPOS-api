<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleRefunded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Sale $sale,
        public readonly User $refundedBy,
        public readonly float $amount,
        public readonly string $reason,
    ) {
    }
}
