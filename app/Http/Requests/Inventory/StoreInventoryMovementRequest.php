<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
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
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'type' => ['required', 'string', 'in:'.implode(',', InventoryMovementType::values())],
            'quantity' => ['required', 'numeric', 'not_in:0', 'min:-9999999999.99', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
