<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manual stock adjustment. "quantity" is the DELTA applied to current
 * stock (positive for adjustments/restocks, negative for losses).
 */
class AdjustStockRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:adjustment,loss,transfer'],
            'quantity' => ['required', 'numeric', 'not_in:0', 'min:-9999999999.99', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.not_in' => 'The adjustment quantity cannot be zero.',
        ];
    }
}
