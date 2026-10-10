<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\ProductService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Queued CSV product import (POST /products/import?async=1).
 * Notifies the requesting user with the final statistics.
 */
class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly int $businessId,
        public readonly string $storedPath,
        public readonly string $onDuplicate,
        public readonly int $userId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(ProductService $products): void
    {
        $absolute = Storage::disk('local')->path($this->storedPath);

        try {
            $result = $products->importCsv($this->businessId, $absolute, $this->onDuplicate);
        } finally {
            Storage::disk('local')->delete($this->storedPath);
        }

        $user = User::find($this->userId);

        $user?->notify(new \App\Notifications\ProductImportCompletedNotification($result));

        Log::info('Product import finished', ['business_id' => $this->businessId] + $result);
    }
}
