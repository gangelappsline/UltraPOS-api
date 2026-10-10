<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBusinessOwner() || $user->isSeller();
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user) && $user->belongsToBusiness($product->business_id);
    }

    public function create(User $user): bool
    {
        return $user->isBusinessOwner();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isBusinessOwner() && $user->belongsToBusiness($product->business_id);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function adjustStock(User $user, Product $product): bool
    {
        return ($user->isBusinessOwner() || $user->isSeller())
            && $user->belongsToBusiness($product->business_id);
    }

    public function import(User $user): bool
    {
        return $user->isBusinessOwner();
    }

    public function export(User $user): bool
    {
        return $user->isBusinessOwner() || $user->isSeller();
    }
}
