<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Events\LowStockDetected;
use App\Exceptions\BusinessLogicException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for every stock mutation.
 *
 * Decision: ALL stock changes funnel through applyDelta() inside a
 * transaction with lockForUpdate() — this serializes concurrent POS
 * terminals and guarantees the invariant "stock never goes negative"
 * (unless the emergency config flag is on).
 */
class InventoryService
{
    /**
     * Apply a signed stock delta to a product (or one of its variants) and
     * write an immutable ledger entry.
     *
     * @param  float  $delta  Signed quantity (+ restock / − consume).
     * @param  Model|null  $reference  Source document (Sale, Purchase, ...)
     */
    public function applyDelta(
        Product $product,
        float $delta,
        InventoryMovementType $type,
        ?User $user = null,
        ?string $notes = null,
        ?Model $reference = null,
        ?ProductVariant $variant = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($product, $delta, $type, $user, $notes, $reference, $variant): InventoryMovement {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            if ($variant !== null) {
                /** @var ProductVariant $lockedVariant */
                $lockedVariant = ProductVariant::query()->whereKey($variant->getKey())->lockForUpdate()->firstOrFail();
                $previous = (float) $lockedVariant->stock;
            } else {
                $lockedVariant = null;
                $previous = (float) $locked->stock;
            }

            $new = round($previous + $delta, 2);

            if ($new < 0 && ! (bool) config('ultrapos.allow_negative_stock', false)) {
                throw new BusinessLogicException(
                    sprintf('Insufficient stock for product "%s" (available: %s, requested: %s)',
                        $locked->name,
                        rtrim(rtrim(number_format($previous, 2, '.', ''), '0'), '.'),
                        rtrim(rtrim(number_format(abs($delta), 2, '.', ''), '0'), '.'),
                    ),
                    [
                        'product_id' => $locked->id,
                        'product_name' => $locked->name,
                        'available' => $previous,
                        'requested' => abs($delta),
                    ]
                );
            }

            if ($lockedVariant !== null) {
                $lockedVariant->forceFill(['stock' => $new])->save();
            } else {
                $locked->forceFill(['stock' => $new])->save();
            }

            $movement = InventoryMovement::create([
                'business_id' => $locked->business_id,
                'product_id' => $locked->id,
                'product_variant_id' => $lockedVariant?->id,
                'user_id' => $user?->id,
                'type' => $type->value,
                'quantity' => $delta,
                'previous_stock' => $previous,
                'new_stock' => $new,
                'reference_type' => $reference !== null ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
            ]);

            $this->detectLowStock($locked, $previous, $new);

            // Keep the in-memory instances fresh for the caller.
            $product->forceFill(['stock' => $new]);
            if ($variant !== null && $lockedVariant !== null) {
                $variant->forceFill(['stock' => $new]);
            }

            return $movement;
        });
    }

    /** Manual adjustment endpoint flow (quantity already signed by caller). */
    public function adjust(
        Product $product,
        InventoryMovementType $type,
        float $quantity,
        ?User $user = null,
        ?string $notes = null,
    ): InventoryMovement {
        if (! in_array($type, [
            InventoryMovementType::ADJUSTMENT,
            InventoryMovementType::LOSS,
            InventoryMovementType::TRANSFER,
        ], true)) {
            throw new BusinessLogicException(
                'Only adjustment, loss and transfer movements can be created manually.'
            );
        }

        $delta = $type === InventoryMovementType::LOSS ? -abs($quantity) : $quantity;

        return $this->applyDelta($product, $delta, $type, $user, $notes);
    }

    /** Consume stock for a sale line (product or variant). */
    public function consumeForSale(Product $product, float $quantity, ?ProductVariant $variant, Model $sale, ?User $user): void
    {
        if (! $product->track_stock) {
            return;
        }

        $this->applyDelta(
            $product,
            -$quantity,
            InventoryMovementType::SALE,
            $user,
            'Sale '.$sale->getAttribute('reference_number'),
            $sale,
            $variant,
        );
    }

    /** Return stock to the shelf (cancellations / refunds). */
    public function returnFromSale(Product $product, float $quantity, ?ProductVariant $variant, Model $sale, ?User $user, string $reason = 'return'): void
    {
        if (! $product->track_stock) {
            return;
        }

        $this->applyDelta(
            $product,
            $quantity,
            InventoryMovementType::RETURN,
            $user,
            $reason.' for sale '.$sale->getAttribute('reference_number'),
            $sale,
            $variant,
        );
    }

    /** Receive purchased quantities into stock. */
    public function receivePurchase(Product $product, float $quantity, ?ProductVariant $variant, Model $purchase, ?User $user): void
    {
        if (! $product->track_stock) {
            return;
        }

        $this->applyDelta(
            $product,
            $quantity,
            InventoryMovementType::PURCHASE,
            $user,
            'Purchase '.$purchase->getAttribute('reference_number'),
            $purchase,
            $variant,
        );
    }

    /** Fire LowStockDetected when the product crosses its minimum. */
    private function detectLowStock(Product $product, float $previous, float $new): void
    {
        if (! $product->track_stock) {
            return;
        }

        $min = (float) $product->min_stock;

        if ($min > 0 && $new <= $min && $previous > $min) {
            LowStockDetected::dispatch($product, $new, $min);
        }
    }

    /**
     * Products below their minimum stock for a tenant.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function lowStockProducts(int $businessId)
    {
        return Product::query()
            ->where('business_id', $businessId)
            ->lowStock()
            ->active()
            ->orderByRaw('(stock - min_stock) ASC')
            ->get();
    }
}
