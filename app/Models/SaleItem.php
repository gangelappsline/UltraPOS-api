<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sale line item with immutable snapshots (product_name, unit_price).
 *
 * @property int $id
 * @property int $sale_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property string $product_name
 * @property float $unit_price
 * @property float $quantity
 * @property float $discount
 * @property float $tax
 * @property float $subtotal
 * @property float $total
 */
class SaleItem extends Model
{
    /** @use HasFactory<\Database\Factories\SaleItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'unit_price',
        'quantity',
        'discount',
        'tax',
        'subtotal',
        'total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** Estimated gross profit of the line (snapshot cost is not stored; uses product cost). */
    public function estimatedProfit(): float
    {
        $cost = $this->product !== null ? (float) $this->product->cost_price : 0.0;

        return round((float) $this->subtotal - ($cost * (float) $this->quantity), 2);
    }
}
