<?php

declare(strict_types=1);

namespace App\Http\Requests\Plans;

use App\Enums\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $planId = (int) $this->route('plan');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash', Rule::unique('plans', 'slug')->ignore($planId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'billing_cycle' => ['sometimes', 'required', 'string', 'in:'.implode(',', BillingCycle::values())],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'limits' => ['nullable', 'array'],
            'limits.*' => ['integer', 'min:-1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
