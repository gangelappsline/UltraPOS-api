<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessLogicException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catalog operations: product CRUD orchestration, slug generation,
 * barcode lookup, CSV import/export.
 */
class ProductService
{
    /**
     * @param  array<string, mixed>  $filters  search|category_id|brand_id|is_active|is_featured|low_stock|in_stock|per_page|sort|direction
     */
    public function list(int $businessId, array $filters = []): LengthAwarePaginator
    {
        return Product::query()
            ->where('business_id', $businessId)
            ->with(['category', 'brand', 'unit'])
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->search((string) $term))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', (int) $id))
            ->when($filters['brand_id'] ?? null, fn ($q, $id) => $q->where('brand_id', (int) $id))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->when(isset($filters['is_featured']), fn ($q) => $q->where('is_featured', (bool) $filters['is_featured']))
            ->when(($filters['low_stock'] ?? false) === '1' || ($filters['low_stock'] ?? false) === true, fn ($q) => $q->lowStock())
            ->when(($filters['in_stock'] ?? false) === '1' || ($filters['in_stock'] ?? false) === true, fn ($q) => $q->inStock())
            ->when($filters['min_price'] ?? null, fn ($q, $v) => $q->where('sale_price', '>=', (float) $v))
            ->when($filters['max_price'] ?? null, fn ($q, $v) => $q->where('sale_price', '<=', (float) $v))
            ->when($filters['expiring_days'] ?? null, fn ($q, $days) => $q->expiringSoon((int) $days))
            ->orderBy(
                in_array($filters['sort'] ?? null, ['name', 'sku', 'sale_price', 'stock', 'created_at'], true)
                    ? (string) $filters['sort']
                    : 'created_at',
                ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
            )
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    /**
     * Create a product (+ optional variants) inside the tenant.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(int $businessId, array $data): Product
    {
        return DB::transaction(function () use ($businessId, $data): Product {
            $this->assertUniqueSku($businessId, (string) $data['sku']);

            /** @var Product $product */
            $product = Product::create([
                'business_id' => $businessId,
                'category_id' => $data['category_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'unit_id' => $data['unit_id'] ?? null,
                'sku' => $data['sku'],
                'barcode' => $data['barcode'] ?? null,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($businessId, (string) $data['name']),
                'description' => $data['description'] ?? null,
                'image' => $data['image'] ?? null,
                'cost_price' => $data['cost_price'],
                'sale_price' => $data['sale_price'],
                'tax_rate' => $data['tax_rate'] ?? 0,
                'stock' => $data['stock'] ?? 0,
                'min_stock' => $data['min_stock'] ?? 0,
                'max_stock' => $data['max_stock'] ?? null,
                'track_stock' => $data['track_stock'] ?? true,
                'is_active' => $data['is_active'] ?? true,
                'is_featured' => $data['is_featured'] ?? false,
                'expires_at' => $data['expires_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            foreach ($data['variants'] ?? [] as $variant) {
                $product->variants()->create([
                    'name' => $variant['name'],
                    'sku' => $variant['sku'],
                    'barcode' => $variant['barcode'] ?? null,
                    'attributes' => $variant['attributes'] ?? null,
                    'cost_price' => $variant['cost_price'] ?? $product->cost_price,
                    'sale_price' => $variant['sale_price'] ?? $product->sale_price,
                    'stock' => $variant['stock'] ?? 0,
                    'is_active' => $variant['is_active'] ?? true,
                ]);
            }

            return $product->load(['category', 'brand', 'unit', 'variants']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        DB::transaction(function () use ($product, $data): void {
            if (isset($data['sku']) && $data['sku'] !== $product->sku) {
                $this->assertUniqueSku($product->business_id, (string) $data['sku'], $product->id);
            }

            if (isset($data['name']) && $data['name'] !== $product->name) {
                $data['slug'] = $this->uniqueSlug($product->business_id, (string) $data['name'], $product->id);
            }

            $product->update(collect($data)->except('variants')->all());
        });

        return $product->refresh()->load(['category', 'brand', 'unit', 'variants']);
    }

    /** Soft delete (products are NEVER hard-deleted: sales reference them). */
    public function delete(Product $product): void
    {
        $product->delete();
    }

    /** Barcode lookup inside the tenant (products + variants). */
    public function findByBarcode(int $businessId, string $barcode): Product
    {
        $product = Product::query()
            ->where('business_id', $businessId)
            ->with(['category', 'brand', 'unit', 'variants'])
            ->where(function ($q) use ($barcode): void {
                $q->where('barcode', $barcode)
                    ->orWhereHas('variants', fn ($vq) => $vq->where('barcode', $barcode));
            })
            ->first();

        if ($product === null) {
            throw new ResourceNotFoundException("No product found for barcode [{$barcode}] in your business.");
        }

        return $product;
    }

    /** @return Collection<int, Product> */
    public function lowStock(int $businessId): Collection
    {
        return Product::query()
            ->where('business_id', $businessId)
            ->with(['category', 'unit'])
            ->lowStock()
            ->orderBy('stock')
            ->get();
    }

    /**
     * Stream a CSV export of the tenant catalog.
     *
     * @return \Closure(): \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportCsv(int $businessId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products-'.now()->format('Ymd-His').'.csv"',
        ];

        return response()->streamDownload(function () use ($businessId): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'sku', 'barcode', 'name', 'category', 'brand', 'unit',
                'cost_price', 'sale_price', 'tax_rate', 'stock', 'min_stock',
                'track_stock', 'is_active', 'expires_at',
            ]);

            Product::query()
                ->where('business_id', $businessId)
                ->with(['category', 'brand', 'unit'])
                ->orderBy('id')
                ->chunk(500, function ($products) use ($handle): void {
                    foreach ($products as $product) {
                        fputcsv($handle, [
                            $product->sku,
                            $product->barcode,
                            $product->name,
                            $product->category?->name,
                            $product->brand?->name,
                            $product->unit?->abbreviation,
                            (string) $product->cost_price,
                            (string) $product->sale_price,
                            (string) $product->tax_rate,
                            (string) $product->stock,
                            (string) $product->min_stock,
                            $product->track_stock ? '1' : '0',
                            $product->is_active ? '1' : '0',
                            $product->expires_at?->toDateString(),
                        ]);
                    }
                });

            fclose($handle);
        }, 'products.csv', $headers);
    }

    /**
     * Synchronous CSV import (queued variant: ProcessProductImport job).
     *
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function importCsv(int $businessId, string $filePath, string $onDuplicate = 'update'): array
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new BusinessLogicException('The uploaded file could not be read.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new BusinessLogicException('The CSV file is empty.');
        }

        $header = array_map(fn ($col) => strtolower(trim((string) $col)), $header);
        $required = ['sku', 'name', 'sale_price'];

        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                fclose($handle);
                throw new BusinessLogicException(
                    'The CSV must contain at least the columns: '.implode(', ', $required).'.'
                );
            }
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $row = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;
            $values = array_combine($header, array_pad($line, count($header), null));

            if ($values === false || empty($values['sku']) || empty($values['name'])) {
                $stats['skipped']++;
                continue;
            }

            try {
                DB::transaction(function () use ($businessId, $values, &$stats): void {
                    $existing = Product::withTrashed()
                        ->where('business_id', $businessId)
                        ->where('sku', (string) $values['sku'])
                        ->first();

                    $payload = [
                        'name' => (string) $values['name'],
                        'sale_price' => (float) ($values['sale_price'] ?? 0),
                        'cost_price' => (float) ($values['cost_price'] ?? 0),
                        'barcode' => $values['barcode'] ?? null,
                        'tax_rate' => (float) ($values['tax_rate'] ?? 0),
                        'min_stock' => (float) ($values['min_stock'] ?? 0),
                        'description' => $values['description'] ?? null,
                        'category_id' => $this->resolveCategoryId($businessId, $values['category'] ?? null),
                        'brand_id' => $this->resolveBrandId($businessId, $values['brand'] ?? null),
                        'unit_id' => $this->resolveUnitId($businessId, $values['unit'] ?? null),
                    ];

                    if ($existing === null) {
                        Product::create([
                            ...$payload,
                            'business_id' => $businessId,
                            'sku' => (string) $values['sku'],
                            'slug' => $this->uniqueSlug($businessId, $payload['name']),
                            'stock' => (float) ($values['stock'] ?? 0),
                            'track_stock' => ($values['track_stock'] ?? '1') === '1',
                            'is_active' => ($values['is_active'] ?? '1') === '1',
                            'expires_at' => ! empty($values['expires_at']) ? $values['expires_at'] : null,
                        ]);
                        $stats['created']++;

                        return;
                    }

                    if ($onDuplicate === 'skip') {
                        $stats['skipped']++;

                        return;
                    }

                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    $existing->update($payload);
                    $stats['updated']++;
                });
            } catch (\Throwable $e) {
                $stats['errors'][] = "Row {$row}: ".$e->getMessage();
            }
        }

        fclose($handle);

        return $stats;
    }

    /* ─────────────────────────── internals ─────────────────────────── */

    private function assertUniqueSku(int $businessId, string $sku, ?int $ignoreId = null): void
    {
        $exists = Product::withTrashed()
            ->where('business_id', $businessId)
            ->where('sku', $sku)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw new BusinessLogicException(
                "The SKU [{$sku}] is already used by another product in your business.",
                ['sku' => ['The sku has already been taken.']]
            );
        }
    }

    private function uniqueSlug(int $businessId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 1;

        while (Product::withTrashed()
            ->where('business_id', $businessId)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    private function resolveCategoryId(int $businessId, mixed $name): ?int
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        $slug = Str::slug($name);

        $category = Category::firstOrCreate(
            ['business_id' => $businessId, 'slug' => $slug],
            ['name' => $name, 'is_active' => true],
        );

        return $category->id;
    }

    private function resolveBrandId(int $businessId, mixed $name): ?int
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        $slug = Str::slug($name);

        $brand = Brand::firstOrCreate(
            ['business_id' => $businessId, 'slug' => $slug],
            ['name' => $name, 'is_active' => true],
        );

        return $brand->id;
    }

    private function resolveUnitId(int $businessId, mixed $abbreviation): ?int
    {
        if (! is_string($abbreviation) || $abbreviation === '') {
            return null;
        }

        $unit = UnitOfMeasure::firstOrCreate(
            ['business_id' => $businessId, 'abbreviation' => $abbreviation],
            ['name' => $abbreviation, 'type' => 'unit', 'is_active' => true],
        );

        return $unit->id;
    }

    /** Nested variant CRUD helpers. */
    public function createVariant(Product $product, array $data): ProductVariant
    {
        return $product->variants()->create($data);
    }

    public function updateVariant(Product $product, ProductVariant $variant, array $data): ProductVariant
    {
        if ($variant->product_id !== $product->id) {
            throw new ResourceNotFoundException('Variant not found for this product.');
        }

        $variant->update($data);

        return $variant->refresh();
    }

    public function deleteVariant(Product $product, ProductVariant $variant): void
    {
        if ($variant->product_id !== $product->id) {
            throw new ResourceNotFoundException('Variant not found for this product.');
        }

        $variant->delete();
    }
}
