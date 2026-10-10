<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashRegisterStatus;
use App\Support\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cash drawer session. A user can only hold ONE open register at a time.
 *
 * @property int $id
 * @property int $business_id
 * @property int $user_id
 * @property CashRegisterStatus $status
 * @property float $opening_amount
 * @property Carbon $opened_at
 */
class CashRegister extends Model
{
    /** @use HasFactory<\Database\Factories\CashRegisterFactory> */
    use BelongsToBusiness;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_amount',
        'closing_amount',
        'expected_amount',
        'difference',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashRegisterStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function isOpen(): bool
    {
        return $this->status === CashRegisterStatus::OPEN;
    }

    /** Sum of signed movements recorded in the session. */
    public function movementsBalance(): float
    {
        return round(
            $this->movements()->get()->sum(
                fn (CashMovement $movement): float => $movement->signedAmount()
            ),
            2
        );
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', CashRegisterStatus::OPEN->value);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
