<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Subscriptions\CancelSubscriptionRequest;
use App\Http\Requests\Subscriptions\UpgradeSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Mail\InvoiceMail;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Subscriptions')]
class SubscriptionController extends BaseController
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    #[OA\Get(path: '/subscriptions/current', operationId: 'subscriptionsCurrent', summary: 'Current subscription of the tenant', tags: ['Subscriptions'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Subscription or null', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function current(Request $request): JsonResponse
    {
        $subscription = $this->subscriptions->current($this->activeBusiness($request));

        return $this->successResponse(
            $subscription !== null
                ? (new SubscriptionResource($subscription->load('plan')))->resolve()
                : null,
            $subscription !== null ? 'Current subscription retrieved' : 'No active subscription'
        );
    }

    #[OA\Get(path: '/subscriptions/history', operationId: 'subscriptionsHistory', summary: 'Subscription history of the tenant', tags: ['Subscriptions'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'History', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))])]
    public function history(Request $request): JsonResponse
    {
        $history = $this->subscriptions->history($this->activeBusiness($request));

        return $this->successResponse(SubscriptionResource::collection($history)->resolve());
    }

    #[OA\Post(path: '/subscriptions/upgrade', operationId: 'subscriptionsUpgrade', summary: 'Change plan (restarts billing period, emails invoice)', tags: ['Subscriptions'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['plan_id'],
            properties: [new OA\Property(property: 'plan_id', type: 'integer', example: 2)],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Plan changed', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Already on this plan', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function upgrade(UpgradeSubscriptionRequest $request): JsonResponse
    {
        $business = $this->activeBusiness($request);
        $owner = $business->owner ?? $this->user($request);

        $subscription = $this->subscriptions->upgrade($business, (int) $request->validated('plan_id'));

        // Queue a simple invoice email for the new period.
        Mail::to($owner->email)->queue(new InvoiceMail(
            $owner,
            $subscription,
            sprintf('INV-%s-%05d', now()->format('Ymd'), $subscription->id),
        ));

        return $this->successResponse(
            (new SubscriptionResource($subscription))->resolve(),
            'Subscription upgraded successfully'
        );
    }

    #[OA\Post(path: '/subscriptions/cancel', operationId: 'subscriptionsCancel', summary: 'Cancel the subscription (period end or immediately)', tags: ['Subscriptions'],
        security: [['passport' => ['business']], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'reason', type: 'string', nullable: true),
            new OA\Property(property: 'immediately', type: 'boolean', default: false),
        ], type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'Cancelled', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'No active subscription', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ])]
    public function cancel(CancelSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->subscriptions->cancel(
            $this->activeBusiness($request),
            $request->boolean('immediately'),
        );

        return $this->successResponse(
            (new SubscriptionResource($subscription))->resolve(),
            'Subscription cancelled successfully'
        );
    }
}
