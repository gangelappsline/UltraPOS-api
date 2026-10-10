<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendDailySalesReport;
use App\Models\Business;
use Illuminate\Console\Command;

/** ultrapos:send-daily-reports {date?} — dispatch one queued digest per business. */
class SendDailySalesReportsCommand extends Command
{
    protected $signature = 'ultrapos:send-daily-reports {date? : Report date (YYYY-MM-DD), defaults to today}';

    protected $description = 'Queue the daily sales report email for every active business';

    public function handle(): int
    {
        $date = $this->argument('date');

        $count = Business::query()
            ->active()
            ->whereHas('owner')
            ->pluck('id')
            ->each(function (int $businessId) use ($date): void {
                SendDailySalesReport::dispatch($businessId, $date !== null ? (string) $date : null);
            })
            ->count();

        $this->info("Dispatched {$count} daily sales report job(s).");

        return self::SUCCESS;
    }
}
