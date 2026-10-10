<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tenant subscription to a plan.
 *
 * @property int $id
 * @property int $business_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $trial_ends_at
 */
class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status->isUsable()
            && ($this->onTrial() || $this->ends_at === null || $this->ends_at->isFuture());
    }

    public function isExpiringWithin(int $days): bool
    {
        return $this->ends_at !== null
            && $this->ends_at->isFuture()
            && $this->ends_at->lte(Carbon::now()->addDays($days));
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SubscriptionStatus::TRIALING->value,
            SubscriptionStatus::ACTIVE->value,
        ]);
    }
}
