<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Business;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Events\BusinessRegistered;
use App\Events\LowStockDetected;
use App\Events\SaleCompleted;
use App\Listeners\NotifyLowStock;
use App\Listeners\SendSaleReceipt;
use App\Listeners\SendWelcomeToNewBusiness;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce HTTPS-generated URLs in production.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Eloquent strictness (N+1, non-fillable and missing-attribute traps).
        //
        // Opt-IN only: these three flags turn every lazy relation access, every
        // key that is not in $fillable and every unread attribute into a 500.
        // That is great for a code base that was written against them and it
        // breaks a fresh install otherwise (e.g. `php artisan migrate --seed`).
        // Enable it while developing with ULTRAPOS_STRICT_MODELS=true.
        if ((bool) config('ultrapos.strict_models', false)) {
            Model::preventLazyLoading();
            Model::preventSilentlyDiscardingAttributes();
            Model::preventAccessingMissingAttributes();
        } else {
            Model::shouldBeStrict(false);
        }

        // Automatic audit trail for the key models (create/update/delete).
        $auditedModels = [
            User::class,
            Business::class,
            Product::class,
            Category::class,
            Customer::class,
            Supplier::class,
            Sale::class,
            Purchase::class,
            Expense::class,
            CashRegister::class,
        ];

        foreach ($auditedModels as $model) {
            $model::observe(AuditObserver::class);
        }

        // Note: AuditLog is intentionally NOT observed (AuditObserver guards
        // against self-auditing internally).

        // Event → listener wiring (explicit for clarity; listeners are queued).
        Event::listen(BusinessRegistered::class, SendWelcomeToNewBusiness::class);
        Event::listen(SaleCompleted::class, SendSaleReceipt::class);
        Event::listen(LowStockDetected::class, NotifyLowStock::class);
    }
}
