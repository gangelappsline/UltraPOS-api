<?php

declare(strict_types=1);

use App\Console\Commands\CleanExpiredTokensCommand;
use App\Console\Commands\SendDailySalesReportsCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Send the daily sales report to every active business owner (queued mails).
Schedule::command(SendDailySalesReportsCommand::class)
    ->dailyAt('23:55')
    ->withoutOverlapping();

// Housekeeping: revoke expired Passport refresh tokens.
Schedule::command(CleanExpiredTokensCommand::class)
    ->daily();
