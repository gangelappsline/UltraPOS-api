<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchases;

use App\Enums\PurchaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseRequest extends FormRequest
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
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('business_id', $businessId)],
            'status' => ['sometimes', 'string', 'in:'.PurchaseStatus::DRAFT->value.','.PurchaseStatus::ORDERED->value],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'purchased_at' => ['nullable', 'date'],

            'items' => ['sometimes', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => ['required_with:items', 'integer', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
