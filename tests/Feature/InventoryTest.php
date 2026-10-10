<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Stock adjustments, the never-negative invariant and the low-stock list. */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_positive_adjustment_increases_stock_and_writes_ledger(): void
    {
        [$business, $owner] = $this->makeBusinessWithOwner();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'stock' => 10,
            'min_stock' => 5,
            'track_stock' => true,
        ]);

        $this->actingAsApi($owner)->postJson("/api/v1/products/{$product->id}/adjust-stock", [
            'type' => 'adjustment',
            'quantity' => 15,
            'notes' => 'Cycle count',
        ])->assertOk();

        $this->assertSame('25.00', (string) $product->refresh()->stock);

        $movement = InventoryMovement::query()->sole();
        $this->assertSame(InventoryMovementType::ADJUSTMENT, $movement->type);
        $this->assertSame('10.00', (string) $movement->previous_stock);
        $this->assertSame('25.00', (string) $movement->new_stock);
        $this->assertSame('15.00', (string) $movement->quantity);
    }

    public function test_stock_can_never_go_negative(): void
    {
        [$business, $owner] = $this->makeBusinessWithOwner();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'stock' => 3,
            'track_stock' => true,
        ]);

        $this->actingAsApi($owner)->postJson("/api/v1/products/{$product->id}/adjust-stock", [
            'type' => 'loss',
            'quantity' => 10, // will be applied as −10
        ])->assertStatus(422)->assertJsonPath('error.code', 'BUSINESS_LOGIC_ERROR');

        $this->assertSame('3.00', (string) $product->refresh()->stock);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_seller_can_adjust_stock(): void
    {
        [$business, ] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'stock' => 20,
            'track_stock' => true,
        ]);

        $this->actingAsApi($seller)->postJson("/api/v1/products/{$product->id}/adjust-stock", [
            'type' => 'adjustment',
            'quantity' => -5,
            'notes' => 'Damaged goods',
        ])->assertOk();

        $this->assertSame('15.00', (string) $product->refresh()->stock);
    }

    public function test_low_stock_endpoint_lists_products_at_or_below_minimum(): void
    {
        [$business, $owner] = $this->makeBusinessWithOwner();

        Product::factory()->create(['business_id' => $business->id, 'stock' => 2, 'min_stock' => 10, 'track_stock' => true]);
        Product::factory()->create(['business_id' => $business->id, 'stock' => 80, 'min_stock' => 10, 'track_stock' => true]);
        Product::factory()->untracked()->create(['business_id' => $business->id, 'stock' => 0]);

        $response = $this->actingAsApi($owner)->getJson('/api/v1/products/low-stock');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_low_stock'));
    }

    public function test_manual_inventory_movement_endpoint_validates_type(): void
    {
        [$business, $owner] = $this->makeBusinessWithOwner();
        $product = Product::factory()->create(['business_id' => $business->id, 'stock' => 10, 'track_stock' => true]);

        // "sale" movements cannot be forged manually.
        $this->actingAsApi($owner)->postJson('/api/v1/inventory/movements', [
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity' => -5,
        ])->assertStatus(422)->assertJsonPath('error.code', 'BUSINESS_LOGIC_ERROR');

        $this->assertSame('10.00', (string) $product->refresh()->stock);
    }
}
