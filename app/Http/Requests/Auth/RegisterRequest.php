<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Public registration: creates a Business (tenant) + its owner User in one
 * atomic operation, and starts a trial subscription on the default plan.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint (rate limited at route level)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:32'],

            'business_name' => ['required', 'string', 'max:190'],
            'business_type' => ['required', 'string', 'in:'.implode(',', BusinessType::values())],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'timezone'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'phone_business' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'size:2'],

            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists.',
            'business_name.required' => 'The business name is required to create your tenant.',
            'business_type.in' => 'The business type must be one of: '.implode(', ', BusinessType::values()).'.',
            'timezone.timezone' => 'The timezone must be a valid identifier (e.g. America/Mexico_City).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'business_name' => 'business name',
            'business_type' => 'business type',
        ];
    }
}
