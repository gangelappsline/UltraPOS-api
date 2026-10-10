<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Passport signs its tokens with the RSA pair in storage/. Fresh clones have
     * none (it is git-ignored), so generate it once per test run instead of
     * failing register/login tests with "Invalid key supplied".
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(Passport::keyPath('oauth-private.key'))) {
            $this->artisan('passport:keys')->assertSuccessful();
        }
    }

    /** Ensure the Spatie roles exist (web guard, matches default guard). */
    protected function seedRoles(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    /** Create a tenant with an owner (role: business). */
    protected function makeBusinessWithOwner(array $businessOverrides = []): array
    {
        $this->seedRoles();

        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole(UserRole::BUSINESS->value);

        /** @var Business $business */
        $business = Business::factory()->create([
            'owner_id' => $owner->id,
            ...$businessOverrides,
        ]);

        $business->users()->attach($owner->id, ['role' => UserRole::BUSINESS->value, 'joined_at' => now()]);
        $owner->flushBusinessCache();

        return [$business, $owner];
    }

    /** Create a seller attached to the given tenant. */
    protected function makeSeller(Business $business): User
    {
        $this->seedRoles();

        /** @var User $seller */
        $seller = User::factory()->create();
        $seller->assignRole(UserRole::SELLER->value);
        $business->users()->attach($seller->id, ['role' => UserRole::SELLER->value, 'joined_at' => now()]);
        $seller->flushBusinessCache();

        return $seller;
    }

    /** Create a global admin (no tenant). */
    protected function makeAdmin(): User
    {
        $this->seedRoles();

        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }

    /** Authenticate a user on the Passport api guard (no real token needed). */
    protected function actingAsApi(User $user): static
    {
        return Passport::actingAs($user, $user->tokenScopes(), 'api');
    }

    /**
     * Provision a Passport personal access client so createToken() works
     * (used by the real register/login flows).
     */
    protected function setUpPersonalAccessClient(): void
    {
        /** @var PassportClient $client */
        $client = app(ClientRepository::class)->createPersonalAccessGrantClient(
            'UltraPOS Testing Personal Access Client',
            'users',
        );

        $this->assertNotNull($client->getKey());
    }
}
