<?php

declare(strict_types=1);

namespace App\Http\Requests\Business;

use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'business_type' => ['sometimes', 'required', 'string', 'in:'.implode(',', BusinessType::values())],
            'email' => ['nullable', 'string', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'timezone'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
