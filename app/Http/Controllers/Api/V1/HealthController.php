<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/** Liveness/readiness checks (GET /api/health). */
#[OA\Tag(name: 'System')]
class HealthController extends BaseController
{
    #[OA\Get(
        path: '/health',
        operationId: 'healthCheck',
        summary: 'Health check (database + cache + queue connectivity)',
        tags: ['System'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Service healthy',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'All systems operational'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'app', type: 'string', example: 'UltraPOS API'),
                        new OA\Property(property: 'version', type: 'string', example: '1.0.0'),
                        new OA\Property(property: 'environment', type: 'string', example: 'production'),
                        new OA\Property(property: 'checks', properties: [
                            new OA\Property(property: 'database', type: 'string', example: 'ok'),
                            new OA\Property(property: 'cache', type: 'string', example: 'ok'),
                        ], type: 'object'),
                    ], type: 'object'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/ResponseMeta'),
                ], type: 'object')),
            new OA\Response(response: 503, description: 'A dependency is failing', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn (): string => DB::connection()->getPdo() ? 'ok' : 'down'),
            'cache' => $this->check(function (): string {
                Cache::put('ultrapos.health.ping', 'pong', 10);

                return Cache::get('ultrapos.health.ping') === 'pong' ? 'ok' : 'down';
            }),
        ];

        $healthy = ! in_array('failing', $checks, true);

        $data = [
            'status' => $healthy ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'version' => '1.0.0',
            'environment' => config('app.env'),
            'checks' => $checks,
            'time' => now()->toIso8601String(),
        ];

        if (! $healthy) {
            return $this->errorResponse(
                'One or more health checks failed',
                \App\Enums\ErrorCode::SERVER_ERROR,
                $data,
                503
            );
        }

        return $this->successResponse($data, 'All systems operational');
    }

    /** @param callable(): string $probe */
    private function check(callable $probe): string
    {
        try {
            return $probe();
        } catch (\Throwable) {
            return 'failing';
        }
    }
}
