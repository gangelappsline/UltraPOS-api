<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Business */
class BusinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id,
            'business_type' => $this->business_type?->value,
            'business_type_label' => $this->business_type?->label(),
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'logo' => $this->logo,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'users_count' => $this->whenCounted('users'),
            'products_count' => $this->whenCounted('products'),
            'current_subscription' => new SubscriptionResource($this->whenLoaded('currentSubscription')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
