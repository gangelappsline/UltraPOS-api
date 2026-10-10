<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Sellable product. Stock is decimal to support weight/volume verticals.
 * "metadata" carries per-business-type attributes (pharmacy, restaurant...).
 *
 * @property int $id
 * @property int $business_id
 * @property string $sku
 * @property string|null $barcode
 * @property string $name
 * @property float $cost_price
 * @property float $sale_price
 * @property float $tax_rate
 * @property float $stock
 * @property float $min_stock
 * @property bool $track_stock
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property-read bool $is_low_stock
 */
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'category_id',
        'brand_id',
        'unit_id',
        'sku',
        'barcode',
        'name',
        'slug',
        'description',
        'image',
        'cost_price',
        'sale_price',
        'tax_rate',
        'stock',
        'min_stock',
        'max_stock',
        'track_stock',
        'is_active',
        'is_featured',
        'expires_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'stock' => 'decimal:2',
            'min_stock' => 'decimal:2',
            'max_stock' => 'decimal:2',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'expires_at' => 'date',
            'metadata' => 'array',
        ];
    }

    /* ─────────────────────────── Relations ─────────────────────────── */

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /* ─────────────────────── Business helpers ──────────────────────── */

    /** Stock is below the configured minimum. */
    public function isLowStock(): bool
    {
        return $this->track_stock && (float) $this->stock <= (float) $this->min_stock;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->isLowStock();
    }

    public function isOutOfStock(): bool
    {
        return $this->track_stock && (float) $this->stock <= 0;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->lte(Carbon::now()->addDays($days));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Gross margin percentage per unit sold. */
    public function marginPercent(): float
    {
        $salePrice = (float) $this->sale_price;

        if ($salePrice <= 0) {
            return 0.0;
        }

        return round((($salePrice - (float) $this->cost_price) / $salePrice) * 100, 2);
    }

    /* ─────────────────────────── Scopes ────────────────────────────── */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('track_stock', false)->orWhere('stock', '>', 0);
        });
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('track_stock', true)
            ->whereColumn('stock', '<=', 'min_stock');
    }

    public function scopeBarcode(Builder $query, string $barcode): Builder
    {
        return $query->where('barcode', $barcode);
    }

    /** Free-text search across name, sku, barcode and description. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$like]);
        });
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', Carbon::now()->addDays($days));
    }
}
