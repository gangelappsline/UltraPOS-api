<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Purchase */
class PurchaseResource extends JsonResource
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
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'balance' => $this->balance(),
            'notes' => $this->notes,
            'purchased_at' => $this->purchased_at?->toDateString(),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => PurchaseItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
