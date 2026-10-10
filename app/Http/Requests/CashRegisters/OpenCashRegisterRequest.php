<?php

declare(strict_types=1);

namespace App\Http\Requests\CashRegisters;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashRegisterRequest extends FormRequest
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
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
