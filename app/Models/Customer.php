<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * POS customer with credit account and loyalty points.
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property float $credit_limit
 * @property float $current_balance
 * @property int $loyalty_points
 */
class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'user_id',
        'name',
        'email',
        'phone',
        'address',
        'tax_id',
        'credit_limit',
        'current_balance',
        'loyalty_points',
        'notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'loyalty_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** Linked user account (optional). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /** Remaining credit available (0 when no credit is configured). */
    public function availableCredit(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->current_balance);
    }

    public function hasCreditEnabled(): bool
    {
        return (float) $this->credit_limit > 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(tax_id, \'\')) LIKE ?', [$like]);
        });
    }
}
