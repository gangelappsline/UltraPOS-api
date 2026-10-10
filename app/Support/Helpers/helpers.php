<?php

declare(strict_types=1);

use App\Support\RequestContext;

if (! function_exists('request_id')) {
    /** Current request trace id (uuid v4), used in the response envelope. */
    function request_id(): string
    {
        return RequestContext::requestId();
    }
}

if (! function_exists('api_money')) {
    /** Round any value to the canonical 2-decimal money representation. */
    function api_money(float|int|string|null $amount): float
    {
        return \App\Support\Money::round($amount ?? 0);
    }
}
