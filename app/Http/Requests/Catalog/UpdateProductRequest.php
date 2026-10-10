<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $productId = (int) $this->route('product');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'sku' => ['sometimes', 'required', 'string', 'max:64',
                Rule::unique('products', 'sku')->where('business_id', $businessId)->ignore($productId),
            ],
            'barcode' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'string', 'max:2048'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $businessId)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('business_id', $businessId)],
            'unit_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')->where('business_id', $businessId)],
            'cost_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'This SKU is already registered in your business.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['tax_rate', 'min_stock', 'max_stock', 'expires_at', 'barcode'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }
}
