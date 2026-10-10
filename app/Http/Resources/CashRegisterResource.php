<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CashRegister */
class CashRegisterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'status' => $this->status?->value,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'opening_amount' => (float) $this->opening_amount,
            'closing_amount' => $this->closing_amount !== null ? (float) $this->closing_amount : null,
            'expected_amount' => $this->expected_amount !== null ? (float) $this->expected_amount : null,
            'difference' => $this->difference !== null ? (float) $this->difference : null,
            'notes' => $this->notes,
            'user' => new UserResource($this->whenLoaded('user')),
            'movements' => CashMovementResource::collection($this->whenLoaded('movements')),
            'movements_count' => $this->whenCounted('movements'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
