<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

/** Payment against a customer credit balance (account receivable). */
class StoreCustomerPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'in:'.implode(',', PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
