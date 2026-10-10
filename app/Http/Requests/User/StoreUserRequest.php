<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', 'in:'.implode(',', $assignable)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Assignable roles are: '.implode(', ', array_diff(UserRole::values(), [UserRole::ADMIN->value])).'.',
        ];
    }
}
