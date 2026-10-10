<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Authentication & account endpoints (Passport OAuth2).
 */
#[OA\Tag(name: 'Auth')]
class AuthController extends BaseController
{
    use ApiResponse;

    public function __construct(private readonly AuthService $auth)
    {
    }

    #[OA\Post(
        path: '/auth/register',
        operationId: 'authRegister',
        summary: 'Register a new business (tenant) + owner account',
        description: 'Public endpoint. Creates the Business, the owner User with role "business", attaches them, starts a trial subscription and returns an access token. Rate limited.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'business_name', 'business_type'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Jane Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@store.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SecurePass1'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'SecurePass1'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+525512345678'),
                    new OA\Property(property: 'business_name', type: 'string', example: 'Demo Store'),
                    new OA\Property(property: 'business_type', type: 'string', enum: ['store', 'grocery', 'pharmacy', 'restaurant', 'hardware', 'other'], example: 'store'),
                    new OA\Property(property: 'currency', type: 'string', example: 'MXN'),
                    new OA\Property(property: 'timezone', type: 'string', example: 'America/Mexico_City'),
                    new OA\Property(property: 'plan_id', type: 'integer', nullable: true, example: 1),
                ],
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Business and user created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Registration successful'),
                        new OA\Property(property: 'data', properties: [
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            new OA\Property(property: 'business', ref: '#/components/schemas/Business'),
                            new OA\Property(property: 'token', ref: '#/components/schemas/TokenPayload'),
                        ], type: 'object'),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/ResponseMeta'),
                    ],
                    type: 'object'
                )),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated());

        return $this->successResponse([
            'user' => (new UserResource($result['user']))->resolve(),
            'business' => (new BusinessResource($result['business']))->resolve(),
            'token' => $result['token'],
        ], 'Registration successful', 201);
    }

    #[OA\Post(
        path: '/auth/login',
        operationId: 'authLogin',
        summary: 'Authenticate and obtain an access token',
        description: 'Public endpoint (throttle:5,1). Returns a Passport token with role-derived scopes.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@ultrapos.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'ChangeMe123!'),
                    new OA\Property(property: 'device_name', type: 'string', example: 'pos-terminal-1'),
                ],
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authenticated',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'token', ref: '#/components/schemas/TokenPayload'),
                    ], type: 'object'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/ResponseMeta'),
                ], type: 'object')),
            new OA\Response(response: 422, description: 'Invalid credentials or business rule violation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 429, description: 'Too many attempts', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login($request->validated());

        return $this->successResponse([
            'user' => (new UserResource($result['user']->load('roles')))->resolve(),
            'token' => $result['token'],
        ], 'Login successful');
    }

    #[OA\Post(
        path: '/auth/refresh',
        operationId: 'authRefresh',
        summary: 'Revoke the current token and issue a new one',
        tags: ['Auth'],
        security: [['passport' => []], ['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'New token issued',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Token refreshed'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'token', ref: '#/components/schemas/TokenPayload'),
                    ], type: 'object'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/ResponseMeta'),
                ], type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        $result = $this->auth->refresh($this->user($request));

        return $this->successResponse($result, 'Token refreshed');
    }

    #[OA\Post(
        path: '/auth/logout',
        operationId: 'authLogout',
        summary: 'Revoke the current access token',
        tags: ['Auth'],
        security: [['passport' => []], ['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($this->user($request));

        return $this->successResponse(null, 'Logged out successfully');
    }

    #[OA\Get(
        path: '/auth/me',
        operationId: 'authMe',
        summary: 'Current authenticated user with roles and active business',
        tags: ['Auth'],
        security: [['passport' => []], ['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Profile',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'business', ref: '#/components/schemas/Business'),
                    ], type: 'object'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/ResponseMeta'),
                ], type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $this->user($request)->load('roles');
        $business = $user->activeBusiness();

        return $this->successResponse([
            'user' => (new UserResource($user))->resolve(),
            'business' => $business !== null ? (new BusinessResource($business))->resolve() : null,
        ], 'Profile retrieved successfully');
    }

    #[OA\Put(
        path: '/auth/profile',
        operationId: 'authUpdateProfile',
        summary: 'Update own profile (name, email, phone, avatar)',
        tags: ['Auth'],
        security: [['passport' => []], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Jane D.'),
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'phone', type: 'string', nullable: true),
            new OA\Property(property: 'avatar', type: 'string', nullable: true),
        ], type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'Profile updated', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $user->update($request->validated());

        return $this->successResponse(
            (new UserResource($user->refresh()->load('roles')))->resolve(),
            'Profile updated successfully'
        );
    }

    #[OA\Put(
        path: '/auth/password',
        operationId: 'authUpdatePassword',
        summary: 'Change own password (revokes every token afterwards)',
        tags: ['Auth'],
        security: [['passport' => []], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['current_password', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Password changed', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Wrong current password or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $user->update(['password' => $request->validated('password')]);

        // Revoke all tokens EXCEPT the current one (issued before the change).
        $currentTokenId = $user->token()?->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->update(['revoked' => true]);

        return $this->successResponse(null, 'Password updated successfully');
    }

    #[OA\Post(
        path: '/auth/forgot-password',
        operationId: 'authForgotPassword',
        summary: 'Request a password reset link (queued email)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email'],
            properties: [new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@store.test')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Reset link sent', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Unknown email', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 429, description: 'Throttled', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $message = $this->auth->sendPasswordResetLink((string) $request->validated('email'));

        return $this->successResponse(null, $message);
    }

    #[OA\Post(
        path: '/auth/reset-password',
        operationId: 'authResetPassword',
        summary: 'Reset a password using the emailed token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['token', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Password reset', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Invalid token', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        /** @var array<string, string> $data */
        $data = $request->validated();
        $message = $this->auth->resetPassword($data);

        return $this->successResponse(null, $message);
    }

    #[OA\Post(
        path: '/auth/verify-email',
        operationId: 'authVerifyEmail',
        summary: 'Verify the account email (signed URL or id+hash)',
        tags: ['Auth'],
        security: [['passport' => []], ['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'url', type: 'string', description: 'The full signed verification URL received by email', example: 'http://localhost:8000/email/verify/1/abc?expires=...&signature=...'),
            new OA\Property(property: 'id', type: 'integer', nullable: true),
            new OA\Property(property: 'hash', type: 'string', nullable: true),
        ], type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'Email verified', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Invalid or expired link', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $this->auth->verifyEmail(
            $this->user($request),
            (string) $request->input('url', ''),
            $request->filled('id') ? (int) $request->input('id') : null,
            $request->filled('hash') ? (string) $request->input('hash') : null,
        );

        return $this->successResponse(null, 'Email verified successfully');
    }

    #[OA\Post(
        path: '/auth/resend-verification',
        operationId: 'authResendVerification',
        summary: 'Resend the verification email (throttled)',
        tags: ['Auth'],
        security: [['passport' => []], ['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Verification email queued', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 422, description: 'Already verified', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 429, description: 'Throttled', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function resendVerification(Request $request): JsonResponse
    {
        $message = $this->auth->resendVerification($this->user($request));

        return $this->successResponse(null, $message);
    }
}
