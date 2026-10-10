<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired after a tenant + owner are created through /auth/register. */
class BusinessRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Business $business,
        public readonly User $owner,
    ) {
    }
}
