<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $cash_register_id
 * @property CashMovementType $type
 * @property float $amount
 */
class CashMovement extends Model
{
    /** @use HasFactory<\Database\Factories\CashMovementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cash_register_id',
        'user_id',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'decimal:2',
            'reference_id' => 'integer',
        ];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    /** Amount with the sign its type implies for the drawer balance. */
    public function signedAmount(): float
    {
        return round((float) $this->amount * $this->type->sign(), 2);
    }

    public function scopeOfType(Builder $query, CashMovementType|string $type): Builder
    {
        return $query->where('type', $type instanceof CashMovementType ? $type->value : $type);
    }
}
