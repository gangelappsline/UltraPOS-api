<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'cost_price' => (float) $this->cost_price,
            'sale_price' => (float) $this->sale_price,
            'tax_rate' => (float) $this->tax_rate,
            'stock' => (float) $this->stock,
            'min_stock' => (float) $this->min_stock,
            'max_stock' => $this->max_stock !== null ? (float) $this->max_stock : null,
            'track_stock' => $this->track_stock,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_low_stock' => $this->isLowStock(),
            'is_out_of_stock' => $this->isOutOfStock(),
            'expires_at' => $this->expires_at?->toDateString(),
            'metadata' => $this->metadata,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'unit' => new UnitOfMeasureResource($this->whenLoaded('unit')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
