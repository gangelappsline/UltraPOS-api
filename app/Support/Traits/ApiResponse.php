<?php

declare(strict_types=1);

namespace App\Support\Traits;

use App\Enums\ErrorCode;
use App\Support\RequestContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;

/**
 * Standardized API response envelope.
 *
 * Success: { success: true, message, data, meta:{timestamp, request_id} }
 * Error:   { success: false, message, error:{code, details}, meta:{...} }
 */
trait ApiResponse
{
    /**
     * Successful response.
     *
     * @param  mixed  $data  JsonResource | array | null
     * @param  array<string, mixed>  $extraMeta  merged into "meta"
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operation completed successfully',
        int $status = 200,
        array $extraMeta = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
            'meta' => array_merge($this->baseMeta(), $extraMeta),
        ], $status);
    }

    /**
     * Error response using a standardized ErrorCode.
     *
     * @param  array<string, mixed>|null  $details
     */
    protected function errorResponse(
        string $message,
        ErrorCode $code = ErrorCode::SERVER_ERROR,
        ?array $details = null,
        ?int $status = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => $code->value,
                'details' => $details ?? (object) [],
            ],
            'meta' => $this->baseMeta(),
        ], $status ?? $code->httpStatus());
    }

    /**
     * Paginated response. The paginator envelope is flattened into
     * data + meta.pagination while keeping the standard envelope.
     *
     * @param  LengthAwarePaginator|ResourceCollection  $paginator
     */
    protected function paginatedResponse(
        LengthAwarePaginator|ResourceCollection $paginator,
        string $message = 'Operation completed successfully',
        int $status = 200
    ): JsonResponse {
        $data = $paginator instanceof ResourceCollection
            ? $paginator->resolve()
            : $paginator->items();

        if ($paginator instanceof ResourceCollection && $paginator->resource instanceof LengthAwarePaginator) {
            $page = $paginator->resource;
        } else {
            $page = $paginator instanceof LengthAwarePaginator ? $paginator : null;
        }

        $meta = $this->baseMeta();
        if ($page instanceof LengthAwarePaginator) {
            $meta['pagination'] = [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'has_more_pages' => $page->hasMorePages(),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => Arr::wrap($data) === $data ? $data : $data,
            'meta' => $meta,
        ], $status);
    }

    /** 204 No Content response (kept as JSON envelope with null data). */
    protected function noContentResponse(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => null,
            'meta' => $this->baseMeta(),
        ], 200);
    }

    /** Meta block shared by every response. */
    private function baseMeta(): array
    {
        return [
            'timestamp' => now()->toIso8601String(),
            'request_id' => RequestContext::requestId(),
        ];
    }
}
