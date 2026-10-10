<?php

declare(strict_types=1);

namespace App\Http\Requests\CashRegisters;

use Illuminate\Foundation\Http\FormRequest;

/** Manual cash-in (deposit) / cash-out (withdrawal) on an open register. */
class StoreCashMovementRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:withdrawal,deposit'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
