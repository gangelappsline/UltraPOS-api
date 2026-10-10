<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
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
        $productId = (int) $this->route('product');

        return [
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['required', 'string', 'max:64',
                Rule::unique('product_variants', 'sku')->where('product_id', $productId),
            ],
            'barcode' => ['nullable', 'string', 'max:64'],
            'attributes' => ['nullable', 'array'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
