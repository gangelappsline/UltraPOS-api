<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Events\BusinessRegistered;
use App\Exceptions\BusinessLogicException;
use App\Models\Business;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Authentication & account lifecycle built on Laravel Passport.
 *
 * Decision: tokens are issued as Passport PERSONAL ACCESS TOKENS carrying
 * role-derived scopes. The OAuth2 password grant (/oauth/token) also works
 * for standard clients — both flows return the same envelope shape.
 * "Refresh" revokes the current token and issues a new one.
 */
class AuthService
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * Public registration: creates Business (tenant) + owner User atomically
     * and starts a trial subscription on the requested/default plan.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, business: Business, token: array<string, mixed>}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            /** @var User $user */
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // 'hashed' cast hashes automatically
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            $user->assignRole(UserRole::BUSINESS->value);

            /** @var Business $business */
            $business = Business::create([
                'owner_id' => $user->id,
                'name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'tax_id' => $data['tax_id'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'timezone' => $data['timezone'] ?? config('app.timezone'),
                'country' => $data['country'] ?? 'US',
                'is_active' => true,
            ]);

            $business->users()->attach($user->id, [
                'role' => UserRole::BUSINESS->value,
                'joined_at' => now(),
            ]);

            // Trial subscription on the chosen (or default) plan.
            $this->subscriptions->startTrial($business, isset($data['plan_id']) ? (int) $data['plan_id'] : null);

            BusinessRegistered::dispatch($business, $user);

            $token = $this->issueToken($user, 'registration');

            return ['user' => $user, 'business' => $business, 'token' => $token];
        });
    }

    /**
     * Validate credentials and issue an access token.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, token: array<string, mixed>}
     */
    public function login(array $data): array
    {
        /** @var User|null $user */
        $user = User::where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw new BusinessLogicException(
                'The provided credentials are incorrect.',
                ['email' => ['The provided credentials are incorrect.']]
            );
        }

        if (! $user->is_active) {
            throw new BusinessLogicException('Your account has been deactivated. Contact your administrator.');
        }

        if ($user->trashed()) {
            throw new BusinessLogicException('Your account no longer exists.');
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $deviceName = isset($data['device_name']) && is_string($data['device_name'])
            ? $data['device_name']
            : 'ultrapos-client';

        return [
            'user' => $user,
            'token' => $this->issueToken($user, $deviceName),
        ];
    }

    /** Revoke the caller's current token and issue a fresh one. */
    public function refresh(User $user): array
    {
        $current = $user->token();

        if ($current !== null) {
            $current->revoke();
        }

        return ['token' => $this->issueToken($user, 'refreshed')];
    }

    /** Revoke the caller's current token. */
    public function logout(User $user): void
    {
        $token = $user->token();

        if ($token !== null) {
            $token->revoke();
        }
    }

    /** Send the password reset link (custom queued mailable). */
    public function sendPasswordResetLink(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw new BusinessLogicException(__($status), ['email' => [__($status)]]);
        }

        return __($status);
    }

    /**
     * Reset a password using token+email.
     *
     * @param  array<string, string>  $data
     */
    public function resetPassword(array $data): string
    {
        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke every token: a password reset logs out all devices.
                $user->tokens()->update(['revoked' => true]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new BusinessLogicException(__($status), ['token' => [__($status)]]);
        }

        return __($status);
    }

    /** Verify the email of the authenticated user (signed URL or id+hash). */
    public function verifyEmail(User $user, string $url = '', ?int $id = null, ?string $hash = null): bool
    {
        if ($user->hasVerifiedEmail()) {
            return true;
        }

        // Mode 1: the client posts back the full signed verification URL.
        if ($url !== '') {
            $signed = Request::create($url);
            $segments = $signed->segments();
            $urlId = is_numeric($segments[count($segments) - 2] ?? null)
                ? (int) $segments[count($segments) - 2]
                : null;
            $urlHash = $segments[count($segments) - 1] ?? null;

            if (URL::hasValidSignature($signed)
                && $urlId === $user->getKey()
                && is_string($urlHash)
                && hash_equals($urlHash, sha1($user->getEmailForVerification()))) {
                $user->markEmailAsVerified();

                return true;
            }

            throw new BusinessLogicException('The verification link is invalid or has expired.');
        }

        // Mode 2: raw id + hash (hash = sha1(email) as generated by Laravel).
        if ($id !== null && $hash !== null
            && $id === $user->getKey()
            && hash_equals($hash, sha1($user->getEmailForVerification()))) {
            $user->markEmailAsVerified();

            return true;
        }

        throw new BusinessLogicException('Invalid email verification payload.');
    }

    /** Resend the verification notification (throttled by the framework). */
    public function resendVerification(User $user): string
    {
        if ($user->hasVerifiedEmail()) {
            throw new BusinessLogicException('Your email address is already verified.');
        }

        $user->notify(new VerifyEmailNotification());

        return 'Verification email sent successfully.';
    }

    /**
     * Issue a Passport personal access token with the user's role scopes.
     *
     * @return array<string, mixed>
     */
    public function issueToken(User $user, string $deviceName = 'ultrapos-client'): array
    {
        $ttlMinutes = (int) config('passport.token_lifetimes.personal_access', 10080);
        $expiresAt = Carbon::now()->addMinutes($ttlMinutes);

        $newToken = $user->createToken($deviceName, $user->tokenScopes(), $expiresAt);

        return [
            'access_token' => $newToken->accessToken,
            'token_type' => 'Bearer',
            'scopes' => $user->tokenScopes(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

}
