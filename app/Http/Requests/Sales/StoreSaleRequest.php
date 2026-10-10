<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POS sale creation. IMPORTANT: the client may send indicative amounts, but
 * totals are ALWAYS recomputed server-side by the SaleService (never trust
 * the client). Only product_id/variant_id + quantity (+ line discount) are
 * consumed from the payload.
 */
class StoreSaleRequest extends FormRequest
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
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', $businessId)],
            'sale_type' => ['sometimes', 'string', 'in:'.implode(',', SaleType::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],

            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],

            'payment' => ['required_without:pay_later', 'nullable', 'array'],
            'payment.method' => ['required_with:payment', 'string', 'in:'.implode(',', PaymentMethod::values())],
            'payment.amount' => ['required_with:payment', 'numeric', 'gt:0'],
            'payment.reference' => ['nullable', 'string', 'max:100'],
            'payment.notes' => ['nullable', 'string', 'max:500'],

            // Credit sales: mark true to skip payment (customer balance grows).
            'pay_later' => ['sometimes', 'boolean'],

            'send_receipt' => ['sometimes', 'boolean'],
            'receipt_email' => ['nullable', 'required_if:send_receipt,true', 'string', 'email:rfc', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'The sale must contain at least one item.',
            'items.*.product_id.exists' => 'One or more products do not belong to your business.',
            'payment.required_without' => 'A payment object is required unless pay_later is true.',
            'receipt_email.required_if' => 'The receipt email is required when send_receipt is true.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['discount'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }
}
