<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auth endpoints: registration (tenant bootstrap), login, /me, envelope
 * shape and unauthenticated access.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_business_owner_and_returns_token(): void
    {
        $this->setUpPersonalAccessClient();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@store.test',
            'password' => 'SecurePass1',
            'password_confirmation' => 'SecurePass1',
            'business_name' => 'Jane\'s Store',
            'business_type' => 'store',
            'currency' => 'MXN',
            'timezone' => 'America/Mexico_City',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'jane@store.test')
            ->assertJsonPath('data.business.name', 'Jane\'s Store')
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['user', 'business', 'token' => ['access_token', 'token_type', 'scopes', 'expires_at']],
                'meta' => ['timestamp', 'request_id'],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'jane@store.test', 'is_active' => 1]);
        $this->assertDatabaseHas('businesses', ['name' => 'Jane\'s Store', 'currency' => 'MXN']);

        $user = User::where('email', 'jane@store.test')->firstOrFail();
        $this->assertTrue($user->hasRole(UserRole::BUSINESS->value));
        $this->assertSame(1, Business::where('owner_id', $user->id)->count());
    }

    public function test_registration_validates_input(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['name', 'email', 'password', 'business_name', 'business_type']);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $this->setUpPersonalAccessClient();
        $this->seedRoles();

        $user = User::factory()->create(['password' => 'SecurePass1']);
        $user->assignRole(UserRole::BUSINESS->value);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'SecurePass1',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token' => ['access_token', 'token_type']]]);

        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->seedRoles();
        $user = User::factory()->create(['password' => 'SecurePass1']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'BUSINESS_LOGIC_ERROR');
    }

    public function test_login_rejects_inactive_users(): void
    {
        $this->seedRoles();
        $user = User::factory()->inactive()->create(['password' => 'SecurePass1']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'SecurePass1',
        ])->assertStatus(422)->assertJsonPath('error.code', 'BUSINESS_LOGIC_ERROR');
    }

    public function test_me_returns_profile_for_authenticated_user(): void
    {
        [$business, $owner] = $this->makeBusinessWithOwner();

        $response = $this->actingAsApi($owner)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.user.email', $owner->email)
            ->assertJsonPath('data.business.id', $business->id);
    }

    public function test_protected_endpoint_rejects_guests_with_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonStructure(['meta' => ['timestamp', 'request_id']]);
    }

    public function test_seller_cannot_access_business_only_endpoint(): void
    {
        [$business, ] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $this->actingAsApi($seller)
            ->getJson('/api/v1/businesses')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_logout_revokes_context_without_crash(): void
    {
        [, $owner] = $this->makeBusinessWithOwner();

        $this->actingAsApi($owner)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
