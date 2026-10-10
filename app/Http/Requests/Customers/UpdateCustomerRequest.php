<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
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
        $customerId = (int) $this->route('customer');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:190',
                Rule::unique('customers', 'email')
                    ->where('business_id', $businessId)
                    ->ignore($customerId),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'loyalty_points' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
