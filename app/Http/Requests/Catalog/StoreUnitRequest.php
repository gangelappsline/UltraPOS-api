<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Enums\UnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:64'],
            'abbreviation' => ['required', 'string', 'max:16',
                Rule::unique('units_of_measure', 'abbreviation')
                    ->where('business_id', $this->user()->active_business_id),
            ],
            'type' => ['required', 'string', 'in:'.implode(',', UnitType::values())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'abbreviation.unique' => 'This abbreviation is already used by another unit in your business.',
        ];
    }
}
