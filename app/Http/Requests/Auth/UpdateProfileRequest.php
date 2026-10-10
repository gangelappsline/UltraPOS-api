<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:190',
                Rule::unique('users', 'email')->ignore($this->user()->id)->withoutTrashed(),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'avatar' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
