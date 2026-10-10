<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tenant isolation: users of business A must NEVER see or affect data of
 * business B, neither through listings nor through direct model access.
 */
class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_scoped_to_the_authenticated_tenant(): void
    {
        [$businessA, $ownerA] = $this->makeBusinessWithOwner();
        [$businessB, ] = $this->makeBusinessWithOwner();

        Product::factory()->count(3)->create(['business_id' => $businessA->id]);
        $foreign = Product::factory()->create(['business_id' => $businessB->id]);

        // Listing only shows tenant A products.
        $response = $this->actingAsApi($ownerA)->getJson('/api/v1/products');
        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
        $this->assertNotContains($foreign->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_direct_access_to_a_foreign_product_returns_404(): void
    {
        [$businessA, $ownerA] = $this->makeBusinessWithOwner();
        [$businessB, ] = $this->makeBusinessWithOwner();

        $foreign = Product::factory()->create(['business_id' => $businessB->id]);

        // Route model binding runs through the global tenant scope → 404.
        $this->actingAsApi($ownerA)
            ->getJson("/api/v1/products/{$foreign->id}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_foreign_barcode_lookup_fails(): void
    {
        [$businessA, $ownerA] = $this->makeBusinessWithOwner();
        [$businessB, ] = $this->makeBusinessWithOwner();

        $foreign = Product::factory()->create([
            'business_id' => $businessB->id,
            'barcode' => '7509999999999',
        ]);

        $this->actingAsApi($ownerA)
            ->getJson('/api/v1/products/barcode/7509999999999')
            ->assertStatus(404);

        $this->assertSame($foreign->business_id, $businessB->id);
    }

    public function test_seller_cannot_sell_a_product_from_another_tenant(): void
    {
        [$businessA, ] = $this->makeBusinessWithOwner();
        [$businessB, ] = $this->makeBusinessWithOwner();
        $sellerA = $this->makeSeller($businessA);

        $foreign = Product::factory()->create([
            'business_id' => $businessB->id,
            'stock' => 100,
            'track_stock' => true,
        ]);

        $this->actingAsApi($sellerA)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $foreign->id, 'quantity' => 1]],
            'payment' => ['method' => 'cash', 'amount' => 100],
        ])->assertStatus(422);

        $this->assertSame(0, Sale::count());
        $this->assertSame('100.00', (string) $foreign->refresh()->stock);
    }

    public function test_customers_and_sales_are_tenant_scoped(): void
    {
        [$businessA, $ownerA] = $this->makeBusinessWithOwner();
        [$businessB, ] = $this->makeBusinessWithOwner();

        Customer::factory()->count(2)->create(['business_id' => $businessA->id]);
        $foreignCustomer = Customer::factory()->create(['business_id' => $businessB->id]);
        Sale::factory()->create(['business_id' => $businessB->id]);

        $this->actingAsApi($ownerA)->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAsApi($ownerA)->getJson("/api/v1/customers/{$foreignCustomer->id}")
            ->assertStatus(404);

        $this->actingAsApi($ownerA)->getJson('/api/v1/sales')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_sees_every_tenant(): void
    {
        [$businessA, ] = $this->makeBusinessWithOwner();
        [$businessB, ] = $this->makeBusinessWithOwner();
        $admin = $this->makeAdmin();

        Product::factory()->create(['business_id' => $businessA->id]);
        Product::factory()->create(['business_id' => $businessB->id]);

        $this->actingAsApi($admin)->getJson('/api/v1/admin/businesses')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAsApi($admin)->getJson('/api/v1/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.businesses.total', 2);
    }

    public function test_user_without_tenant_gets_403_on_business_scoped_routes(): void
    {
        $this->seedRoles();
        /** @var \App\Models\User $lonely */
        $lonely = \App\Models\User::factory()->create();
        $lonely->assignRole(\App\Enums\UserRole::SELLER->value);

        $this->actingAsApi($lonely)
            ->getJson('/api/v1/products')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
