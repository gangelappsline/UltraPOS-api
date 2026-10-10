<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        $assignable = array_diff(UserRole::values(), [UserRole::ADMIN->value]);
        $userId = (int) $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:190',
                Rule::unique('users', 'email')->ignore($userId)->withoutTrashed(),
            ],
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:32'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'role' => ['sometimes', 'required', 'string', 'in:'.implode(',', $assignable)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
