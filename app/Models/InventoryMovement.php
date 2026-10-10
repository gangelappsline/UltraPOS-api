<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Support\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable stock ledger entry. Never updated or deleted.
 *
 * @property int $id
 * @property int $business_id
 * @property int $product_id
 * @property InventoryMovementType $type
 * @property float $quantity
 * @property float $previous_stock
 * @property float $new_stock
 */
class InventoryMovement extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryMovementFactory> */
    use BelongsToBusiness;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'product_id',
        'product_variant_id',
        'user_id',
        'type',
        'quantity',
        'previous_stock',
        'new_stock',
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
            'type' => InventoryMovementType::class,
            'quantity' => 'decimal:2',
            'previous_stock' => 'decimal:2',
            'new_stock' => 'decimal:2',
            'reference_id' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Polymorphic source document (Sale, Purchase, ...). */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    public function scopeOfType(Builder $query, InventoryMovementType|string $type): Builder
    {
        return $query->where('type', $type instanceof InventoryMovementType ? $type->value : $type);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
