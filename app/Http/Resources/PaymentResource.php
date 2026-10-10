<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'payable_type' => class_basename((string) $this->payable_type),
            'payable_id' => $this->payable_id,
            'method' => $this->method?->value,
            'amount' => (float) $this->amount,
            'reference' => $this->reference,
            'status' => $this->status?->value,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'notes' => $this->notes,
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
