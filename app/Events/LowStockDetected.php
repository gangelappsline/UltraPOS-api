<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when a product crosses below its configured min_stock. */
class LowStockDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly float $currentStock,
        public readonly float $minStock,
    ) {
    }
}
