<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Register a payment against a payable document (sale, customer balance,
 * purchase). paid_amount / payment_status of the document are updated by
 * the PaymentService — never by the client.
 */
class StorePaymentRequest extends FormRequest
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
            'payable_type' => ['required', 'string', 'in:sale,customer,purchase'],
            'payable_id' => ['required', 'integer'],
            'method' => ['required', 'string', 'in:'.implode(',', PaymentMethod::values())],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    /** Resolve the morph class of the payable document. */
    public function payableClass(): string
    {
        return match ($this->input('payable_type')) {
            'sale' => Sale::class,
            'customer' => Customer::class,
            'purchase' => Purchase::class,
            default => Sale::class,
        };
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $class = $this->payableClass();
            $id = (int) $this->input('payable_id');

            // Tenant isolation: the global scope guarantees the document
            // belongs to the authenticated user's business.
            if (! $class::query()->whereKey($id)->exists()) {
                $validator->errors()->add(
                    'payable_id',
                    'The selected document does not exist in your business.'
                );
            }
        });
    }
}
