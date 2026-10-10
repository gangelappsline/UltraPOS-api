<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Policies\BusinessPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SalePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Business::class => BusinessPolicy::class,
        Customer::class => CustomerPolicy::class,
        Product::class => ProductPolicy::class,
        Sale::class => SalePolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Passport token scopes mirror the system roles (contextual role
        // of the token) so clients can be restricted at the OAuth layer.
        Passport::tokensCan([
            UserRole::ADMIN->scope() => 'Global administrator',
            UserRole::BUSINESS->scope() => 'Business owner / manager',
            UserRole::SELLER->scope() => 'Seller / cashier',
            UserRole::CUSTOMER->scope() => 'End customer',
            'sales.create' => 'Create POS sales',
            'sales.read' => 'Read sales',
            'products.read' => 'Read products',
            'products.write' => 'Create and update products',
            'inventory.manage' => 'Manage inventory',
            'reports.read' => 'Read reports',
        ]);

        // Token lifetimes from config (config/passport.php → .env).
        $minutes = static fn (string $key, int $default): \DateInterval => new \DateInterval(
            sprintf('PT%dM', max(1, (int) config("passport.token_lifetimes.{$key}", $default)))
        );

        Passport::tokensExpireIn($minutes('access', 1440));
        Passport::refreshTokensExpireIn($minutes('refresh', 43200));
        Passport::personalAccessTokensExpireIn($minutes('personal_access', 10080));

        // Passport 13 ships the OAuth2 password grant DISABLED. The docs
        // (Swagger UI "Authorize" button) and any stock OAuth2 client expect
        // POST /oauth/token with grant_type=password, so opt in explicitly.
        if ((bool) config('ultrapos.password_grant_enabled', true)) {
            Passport::enablePasswordGrant();
        }

        // Super admin gate: "admin" role bypasses every policy.
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole(UserRole::ADMIN->value) ? true : null;
        });
    }
}
