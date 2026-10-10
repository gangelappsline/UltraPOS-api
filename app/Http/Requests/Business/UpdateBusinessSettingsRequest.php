<?php

declare(strict_types=1);

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk upsert of tenant key/value settings (settings table).
 * Payload: { "settings": { "receipt.footer": "Thank you!", "tax.default_rate": 16 } , "group": "general" }
 */
class UpdateBusinessSettingsRequest extends FormRequest
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
            'settings' => ['required', 'array', 'min:1'],
            'settings.*' => ['present'],
            'group' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.required' => 'At least one setting key/value pair is required.',
        ];
    }
}
