<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns (or propagates) a request trace id, exposed as X-Request-Id and
 * embedded in the "meta.request_id" field of every API response envelope.
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id');

        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }

        RequestContext::setRequestId($requestId);
        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    /** Reset static state when the kernel terminates (queue/octane safety). */
    public function terminate(Request $request, Response $response): void
    {
        RequestContext::flush();
    }
}
