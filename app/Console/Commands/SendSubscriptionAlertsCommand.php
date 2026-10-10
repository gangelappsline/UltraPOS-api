<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringMail;
use App\Models\Business;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * ultrapos:subscription-alerts — expires overdue subscriptions and mails
 * owners whose plan ends within the configured warning window.
 */
class SendSubscriptionAlertsCommand extends Command
{
    protected $signature = 'ultrapos:subscription-alerts';

    protected $description = 'Expire overdue subscriptions and warn owners about expiring ones';

    public function handle(SubscriptionService $subscriptions): int
    {
        $expired = $subscriptions->expireOverdue();

        $warningDays = (int) config('ultrapos.subscription_warning_days', 7);
        $deadline = Carbon::now()->addDays($warningDays);

        $expiring = Subscription::query()
            ->with(['plan', 'business.owner'])
            ->usable()
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [Carbon::now(), $deadline])
            ->get();

        foreach ($expiring as $subscription) {
            /** @var Business|null $business */
            $business = $subscription->business;

            if ($business?->owner !== null) {
                Mail::to($business->owner->email)
                    ->queue(new SubscriptionExpiringMail($business->owner, $subscription));
            }
        }

        $this->info("Expired {$expired} subscription(s); mailed {$expiring->count()} expiring alert(s).");

        return self::SUCCESS;
    }
}
