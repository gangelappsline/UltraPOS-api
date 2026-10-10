<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BusinessType;
use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo tenant with owner, seller, catalog and a customer — handy for
 * smoke-testing the POS flow after `php artisan migrate --seed`.
 */
class DemoBusinessSeeder extends Seeder
{
    public function run(): void
    {
        // ── Owner ──────────────────────────────────────────────────────
        $owner = $this->seedUser(
            env('DEMO_BUSINESS_EMAIL', 'demo@ultrapos.test'),
            'Demo Owner',
            env('DEMO_BUSINESS_PASSWORD', 'ChangeMe123!'),
            UserRole::BUSINESS,
        );

        // ── Seller ─────────────────────────────────────────────────────
        $seller = $this->seedUser('seller@ultrapos.test', 'Demo Seller', 'ChangeMe123!', UserRole::SELLER);

        // ── Tenant ─────────────────────────────────────────────────────
        $business = Business::firstOrCreate(
            ['owner_id' => $owner->id, 'name' => env('DEMO_BUSINESS_NAME', 'Demo Store')],
            [
                'legal_name' => 'Demo Store LLC',
                'tax_id' => 'DEMO000001',
                'business_type' => BusinessType::STORE->value,
                'email' => 'contact@demo.test',
                'phone' => '+525500000001',
                'address' => 'Av. Reforma 123',
                'city' => 'Mexico City',
                'state' => 'CDMX',
                'country' => 'MX',
                'postal_code' => '06600',
                'currency' => 'MXN',
                'timezone' => 'America/Mexico_City',
                'is_active' => true,
                'settings' => ['locale' => 'en', 'receipt_footer' => 'Thank you for shopping with us!'],
            ],
        );

        $business->users()->syncWithoutDetaching([
            $owner->id => ['role' => UserRole::BUSINESS->value, 'joined_at' => now()],
            $seller->id => ['role' => UserRole::SELLER->value, 'joined_at' => now()],
        ]);

        // ── Units ──────────────────────────────────────────────────────
        $units = [];
        foreach ([
            ['Piece', 'pc', UnitType::UNIT],
            ['Kilogram', 'kg', UnitType::WEIGHT],
            ['Litre', 'lt', UnitType::VOLUME],
        ] as [$name, $abbr, $type]) {
            $units[$abbr] = UnitOfMeasure::firstOrCreate(
                ['business_id' => $business->id, 'abbreviation' => $abbr],
                ['name' => $name, 'type' => $type->value, 'is_active' => true],
            );
        }

        // ── Categories ─────────────────────────────────────────────────
        $categories = [];
        foreach (['Beverages', 'Snacks', 'Dairy', 'Cleaning'] as $i => $name) {
            $categories[$name] = Category::firstOrCreate(
                ['business_id' => $business->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i, 'is_active' => true],
            );
        }

        // ── Supplier ───────────────────────────────────────────────────
        Supplier::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Distribuidora Central'],
            [
                'contact_name' => 'Laura Pérez',
                'email' => 'ventas@distribuidora.test',
                'phone' => '+525500000002',
                'is_active' => true,
            ],
        );

        // ── Products ───────────────────────────────────────────────────
        $catalog = [
            ['Cola 600ml', 'BEV-001', '7501000000011', 8.50, 15.00, 16, 120, 'Beverages', 'pc'],
            ['Water 1L', 'BEV-002', '7501000000028', 5.00, 10.00, 0, 200, 'Beverages', 'pc'],
            ['Potato chips 45g', 'SNK-001', '7501000000035', 9.00, 17.50, 16, 80, 'Snacks', 'pc'],
            ['Milk 1L', 'DAY-001', '7501000000042', 16.00, 26.00, 0, 60, 'Dairy', 'lt'],
            ['Panela cheese 400g', 'DAY-002', '7501000000059', 38.00, 62.00, 0, 25, 'Dairy', 'pc'],
            ['Dish soap 750ml', 'CLN-001', '7501000000066', 21.00, 36.00, 16, 40, 'Cleaning', 'pc'],
            ['Rice (bulk)', 'GRO-001', '7501000000073', 18.00, 28.00, 0, 150, 'Snacks', 'kg'],
        ];

        foreach ($catalog as [$name, $sku, $barcode, $cost, $price, $tax, $stock, $cat, $unit]) {
            Product::firstOrCreate(
                ['business_id' => $business->id, 'sku' => $sku],
                [
                    'barcode' => $barcode,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'category_id' => $categories[$cat]->id,
                    'unit_id' => $units[$unit]->id,
                    'cost_price' => $cost,
                    'sale_price' => $price,
                    'tax_rate' => $tax,
                    'stock' => $stock,
                    'min_stock' => 10,
                    'max_stock' => 300,
                    'track_stock' => true,
                    'is_active' => true,
                ],
            );
        }

        // ── A low-stock product to see alerts immediately ─────────────
        Product::firstOrCreate(
            ['business_id' => $business->id, 'sku' => 'SNK-002'],
            [
                'name' => 'Peanuts 100g',
                'slug' => 'peanuts-100g',
                'barcode' => '7501000000080',
                'category_id' => $categories['Snacks']->id,
                'unit_id' => $units['pc']->id,
                'cost_price' => 7.00,
                'sale_price' => 14.00,
                'tax_rate' => 16,
                'stock' => 3,
                'min_stock' => 10,
                'track_stock' => true,
                'is_active' => true,
            ],
        );

        // ── Customer with credit ───────────────────────────────────────
        Customer::firstOrCreate(
            ['business_id' => $business->id, 'email' => 'john@mail.test'],
            [
                'name' => 'John Smith',
                'phone' => '+525500000003',
                'credit_limit' => 5000,
                'current_balance' => 0,
                'loyalty_points' => 120,
                'is_active' => true,
            ],
        );
    }

    /**
     * Creates (or reuses) a seeded user.
     *
     * email_verified_at cannot go through mass assignment: it is deliberately
     * absent from User::$fillable so no endpoint can self-verify an address.
     * Seeders are trusted, hence forceFill().
     */
    private function seedUser(string $email, string $name, string $password, UserRole $role): User
    {
        /** @var User $user */
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'is_active' => true,
            ],
        );

        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->syncRoles([$role->value]);

        return $user;
    }
}
