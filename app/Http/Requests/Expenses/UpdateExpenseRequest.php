<?php

declare(strict_types=1);

namespace App\Http\Requests\Expenses;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
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
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'expense_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'receipt' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
