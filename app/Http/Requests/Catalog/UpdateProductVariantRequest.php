<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
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
        $variantId = (int) $this->route('variant');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'sku' => ['sometimes', 'required', 'string', 'max:64',
                Rule::unique('product_variants', 'sku')
                    ->where('product_id', $productId)
                    ->ignore($variantId),
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
