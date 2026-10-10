<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Exceptions\BusinessLogicException;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Tenant subscription lifecycle: trial on registration, upgrades and
 * cancellations. Billing itself is out of scope (payment gateway hooks
 * can be added later without changing this contract).
 */
class SubscriptionService
{
    /** Start the trial subscription used at business registration. */
    public function startTrial(Business $business, ?int $planId = null): Subscription
    {
        $plan = $planId !== null
            ? Plan::active()->findOrFail($planId)
            : Plan::active()->orderBy('price')->first();

        if ($plan === null) {
            // No plans configured yet: skip silently (admin must seed plans).
            return new Subscription(['business_id' => $business->id, 'status' => SubscriptionStatus::TRIALING->value]);
        }

        return Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::TRIALING->value,
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays((int) config('ultrapos.trial_days', 14)),
            'ends_at' => now()->addDays((int) config('ultrapos.trial_days', 14)),
        ]);
    }

    public function current(Business $business): ?Subscription
    {
        return Subscription::query()
            ->where('business_id', $business->id)
            ->with('plan')
            ->usable()
            ->latest('id')
            ->first();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Subscription> */
    public function history(Business $business)
    {
        return Subscription::query()
            ->where('business_id', $business->id)
            ->with('plan')
            ->latest('id')
            ->get();
    }

    /** Move the tenant to a different plan, restarting the billing period. */
    public function upgrade(Business $business, int $planId): Subscription
    {
        $plan = Plan::active()->findOrFail($planId);

        return DB::transaction(function () use ($business, $plan): Subscription {
            $current = $this->current($business);

            if ($current !== null && $current->plan_id === $plan->id) {
                throw new BusinessLogicException('The business is already subscribed to this plan.');
            }

            if ($current !== null) {
                $current->forceFill(['status' => SubscriptionStatus::CANCELLED->value])->save();
            }

            return Subscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::ACTIVE->value,
                'starts_at' => now(),
                'ends_at' => now()->addMonths($plan->billing_cycle->months()),
            ])->load('plan');
        });
    }

    /**
     * Cancel: at period end by default, or immediately when requested.
     */
    public function cancel(Business $business, bool $immediately = false): Subscription
    {
        $subscription = $this->current($business);

        if ($subscription === null) {
            throw new BusinessLogicException('The business does not have an active subscription.');
        }

        $subscription->forceFill([
            'status' => SubscriptionStatus::CANCELLED->value,
            'ends_at' => $immediately ? now() : ($subscription->ends_at ?? now()),
        ])->save();

        return $subscription->refresh()->load('plan');
    }

    /** Mark subscriptions past their ends_at as expired (scheduled job). */
    public function expireOverdue(): int
    {
        return Subscription::query()
            ->usable()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::EXPIRED->value]);
    }
}
