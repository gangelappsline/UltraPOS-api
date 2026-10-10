<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Variant of a product (size/color/presentation) with its own sku & stock.
 * Tenancy flows through the parent product (business scope is applied via
 * whereHas when listing variants directly).
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property float $sale_price
 * @property float $stock
 * @property array<string, mixed>|null $attributes
 */
class ProductVariant extends Model
{
    /** @use HasFactory<\Database\Factories\ProductVariantFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'attributes',
        'cost_price',
        'sale_price',
        'stock',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
