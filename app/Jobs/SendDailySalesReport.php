<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\DailySalesReportMail;
use App\Models\Business;
use App\Services\SaleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/** Queued daily digest for one business (dispatched by the scheduler). */
class SendDailySalesReport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $businessId,
        public readonly ?string $date = null,
    ) {
        $this->onQueue('reports');
    }

    public function handle(SaleService $sales): void
    {
        $business = Business::with('owner')->find($this->businessId);

        if ($business === null || $business->owner === null || ! $business->is_active) {
            return;
        }

        $summary = $sales->dailySummary(
            $business->id,
            $this->date !== null ? Carbon::parse($this->date) : Carbon::today(),
        );

        if ((int) $summary['sales_count'] === 0) {
            return; // no noise on empty days
        }

        Mail::to($business->owner->email)
            ->queue(new DailySalesReportMail($business->owner, $business, $summary));
    }
}
