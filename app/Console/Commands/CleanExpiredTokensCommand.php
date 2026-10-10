<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** ultrapos:clean-expired-tokens — housekeeping for revoked/expired OAuth rows. */
class CleanExpiredTokensCommand extends Command
{
    protected $signature = 'ultrapos:clean-expired-tokens';

    protected $description = 'Delete revoked or expired Passport access/refresh tokens';

    public function handle(): int
    {
        $now = Carbon::now();

        $access = DB::table('oauth_access_tokens')
            ->where(function ($q) use ($now): void {
                $q->where('revoked', true)
                    ->orWhere(fn ($qq) => $qq->whereNotNull('expires_at')->where('expires_at', '<', $now));
            })
            ->delete();

        $refresh = DB::table('oauth_refresh_tokens')
            ->where(function ($q) use ($now): void {
                $q->where('revoked', true)
                    ->orWhere(fn ($qq) => $qq->whereNotNull('expires_at')->where('expires_at', '<', $now));
            })
            ->delete();

        $this->info("Deleted {$access} access token(s) and {$refresh} refresh token(s).");

        return self::SUCCESS;
    }
}
