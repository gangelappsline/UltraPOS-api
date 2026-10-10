<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingCycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SaaS plan (global entity, managed by admins).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property float $price
 * @property BillingCycle $billing_cycle
 * @property array<int, string>|null $features
 * @property array<string, mixed>|null $limits
 */
class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'features',
        'limits',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'billing_cycle' => BillingCycle::class,
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
