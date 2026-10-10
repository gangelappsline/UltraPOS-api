<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CSV bulk import. Required headers:
 * sku,name,sale_price,cost_price[,barcode,category,brand,unit,stock,min_stock,tax_rate,description]
 */
class ImportProductsRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'on_duplicate' => ['sometimes', 'string', 'in:skip,update,fail'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'The import file must be a CSV.',
            'file.max' => 'The import file cannot exceed 10 MB.',
        ];
    }
}
