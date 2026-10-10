<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Support\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Supplier purchase order.
 *
 * @property int $id
 * @property int $business_id
 * @property string $reference_number
 * @property PurchaseStatus $status
 * @property float $total
 */
class Purchase extends Model
{
    /** @use HasFactory<\Database\Factories\PurchaseFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'supplier_id',
        'user_id',
        'reference_number',
        'status',
        'subtotal',
        'tax',
        'discount',
        'total',
        'paid_amount',
        'notes',
        'purchased_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'purchased_at' => 'date',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /** Outstanding amount of the purchase order. */
    public function balance(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [PurchaseStatus::DRAFT, PurchaseStatus::ORDERED], true);
    }

    public function scopeStatus(Builder $query, PurchaseStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof PurchaseStatus ? $status->value : $status);
    }
}
