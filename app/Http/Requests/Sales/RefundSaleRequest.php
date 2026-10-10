<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Full or partial refund of a completed sale. When "items" is omitted the
 * whole sale is refunded.
 */
class RefundSaleRequest extends FormRequest
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
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required_with:items', 'integer', 'exists:sale_items,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'refund_method' => ['nullable', 'string', 'in:'.implode(',', PaymentMethod::values())],
            'restock' => ['sometimes', 'boolean'],
        ];
    }
}
