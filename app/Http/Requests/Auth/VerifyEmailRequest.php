<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts either the signed verification URL ("url" field) or a raw
 * id + hash pair, so any client (web/mobile/POS) can confirm the email.
 */
class VerifyEmailRequest extends FormRequest
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
            'url' => ['required_without_all:id,hash', 'string', 'url'],
            'id' => ['required_with:hash', 'integer'],
            'hash' => ['required_with:id', 'string'],
        ];
    }
}
