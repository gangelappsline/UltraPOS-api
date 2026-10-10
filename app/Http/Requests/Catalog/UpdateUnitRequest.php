<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Enums\UnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
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
        $unitId = (int) $this->route('unit');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:64'],
            'abbreviation' => ['sometimes', 'required', 'string', 'max:16',
                Rule::unique('units_of_measure', 'abbreviation')
                    ->where('business_id', $this->user()->active_business_id)
                    ->ignore($unitId),
            ],
            'type' => ['sometimes', 'required', 'string', 'in:'.implode(',', UnitType::values())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
