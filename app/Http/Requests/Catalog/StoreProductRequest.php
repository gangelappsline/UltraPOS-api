<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = $this->user()->active_business_id;

        return [
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['required', 'string', 'max:64',
                Rule::unique('products', 'sku')->where('business_id', $businessId),
            ],
            'barcode' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'string', 'max:2048'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $businessId)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('business_id', $businessId)],
            'unit_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')->where('business_id', $businessId)],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stock' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0', 'gte:min_stock'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'variants' => ['nullable', 'array', 'max:200'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:190'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:64'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'This SKU is already registered in your business.',
            'sale_price.required' => 'The sale price is required.',
            'max_stock.gte' => 'The maximum stock must be greater than or equal to the minimum stock.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalize optional numerics so empty strings don't fail validation.
        foreach (['tax_rate', 'stock', 'min_stock', 'max_stock', 'expires_at', 'barcode'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }
}
