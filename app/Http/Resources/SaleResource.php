<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Sale */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'reference_number' => $this->reference_number,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'sale_type' => $this->sale_type?->value,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'change_amount' => (float) $this->change_amount,
            'balance' => $this->balance(),
            'notes' => $this->notes,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'user' => new UserResource($this->whenLoaded('user')),
            'business' => new BusinessResource($this->whenLoaded('business')),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
