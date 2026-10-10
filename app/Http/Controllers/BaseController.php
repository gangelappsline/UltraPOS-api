<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BusinessLogicException;
use App\Models\Business;
use App\Models\User;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Base controller for every API controller.
 *
 * Decision: controllers stay THIN — they validate (via FormRequests),
 * authorize (via Policies) and delegate business logic to Services;
 * output is always transformed through JsonResources and returned with
 * the standard envelope provided by the ApiResponse trait.
 */
abstract class BaseController extends Controller
{
    use ApiResponse;

    /** The authenticated user (guaranteed by auth:api on protected routes). */
    protected function user(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            throw new BusinessLogicException('Unauthenticated.');
        }

        return $user;
    }

    /** Active tenant id of the authenticated user (403 when missing). */
    protected function businessId(Request $request): int
    {
        $business = $this->activeBusiness($request);

        return (int) $business->getKey();
    }

    /** Active tenant model of the authenticated user. */
    protected function activeBusiness(Request $request): Business
    {
        $business = $request->user()?->activeBusiness();

        if ($business === null) {
            throw new BusinessLogicException(
                'Your account is not associated with an active business.',
                null
            );
        }

        return $business;
    }
}
