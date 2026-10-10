<?php

declare(strict_types=1);

use App\Console\Commands\CleanExpiredTokensCommand;
use App\Console\Commands\SendDailySalesReportsCommand;
use App\Console\Commands\SendSubscriptionAlertsCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Driven by `php artisan schedule:work` (dev) or a cron entry running
| `* * * * * php /path/artisan schedule:run >> /dev/null 2>&1`.
| In the Docker stack the `scheduler` service loops schedule:run every minute.
|
| NOTE: these entries reference the command classes, which are registered by
| bootstrap/app.php -> withCommands([...]). Without that call the commands are
| not in the Artisan application and every schedule entry fails with
| "There are no commands defined in the ... namespace".
|
*/

// Send the daily sales report to every active business owner (queued mails).
Schedule::command(SendDailySalesReportsCommand::class)
    ->dailyAt('23:55')
    ->withoutOverlapping();

// Expire overdue subscriptions + warn owners whose plan ends soon.
Schedule::command(SendSubscriptionAlertsCommand::class)
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Housekeeping: revoke expired Passport refresh tokens.
Schedule::command(CleanExpiredTokensCommand::class)
    ->daily()
    ->withoutOverlapping();
