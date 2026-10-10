<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The critical POS flow: server-side totals, stock consumption, payments,
 * change, credit limits, cancellation and refunds.
 */
class SaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_seller_creates_a_completed_sale_with_cash_payment(): void
    {
        [$business, ] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'sale_price' => 15.00,
            'cost_price' => 8.00,
            'tax_rate' => 16.00,
            'stock' => 50,
            'min_stock' => 5,
            'track_stock' => true,
        ]);

        $response = $this->actingAsApi($seller)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payment' => ['method' => 'cash', 'amount' => 100],
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        /** @var Sale $sale */
        $sale = Sale::query()->with('items')->sole();

        // Server-side totals: 3 × 15.00 = 45.00 + 16% tax (7.20) = 52.20
        $this->assertSame('45.00', (string) $sale->subtotal);
        $this->assertSame('7.20', (string) $sale->tax);
        $this->assertSame('52.20', (string) $sale->total);
        $this->assertSame('52.20', (string) $sale->paid_amount);
        $this->assertSame('47.80', (string) $sale->change_amount); // 100 − 52.20
        $this->assertSame(SaleStatus::COMPLETED, $sale->status);
        $this->assertSame(PaymentStatus::PAID, $sale->payment_status);
        $this->assertSame($seller->id, $sale->user_id);

        // Stock consumed + ledger written.
        $this->assertSame('47.00', (string) $product->refresh()->stock);
        $movement = InventoryMovement::query()->sole();
        $this->assertSame(InventoryMovementType::SALE, $movement->type);
        $this->assertSame('-3.00', (string) $movement->quantity);
        $this->assertSame($sale->id, $movement->reference_id);

        // Snapshot line data.
        $item = $sale->items->sole();
        $this->assertSame($product->name, $item->product_name);
        $this->assertSame('15.00', (string) $item->unit_price);
    }

    public function test_client_sent_prices_are_ignored_totals_are_recomputed(): void
    {
        [$business, ] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'sale_price' => 100.00,
            'tax_rate' => 0,
            'stock' => 10,
            'track_stock' => true,
        ]);

        // A malicious client tries to send its own unit price / totals.
        $this->actingAsApi($seller)->postJson('/api/v1/sales', [
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 0.01,   // ignored
                'total' => 0.01,        // ignored
            ]],
            'subtotal' => 0.01,          // ignored
            'total' => 0.01,             // ignored
            'payment' => ['method' => 'card', 'amount' => 100],
        ])->assertCreated();

        /** @var Sale $sale */
        $sale = Sale::query()->sole();
        $this->assertSame('100.00', (string) $sale->total);
        $this->assertSame('100.00', (string) $sale->items->sole()->unit_price);
    }

    public function test_sale_fails_when_stock_is_insufficient(): void
    {
        [$business, ] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'stock' => 2,
            'track_stock' => true,
        ]);

        $response = $this->actingAsApi($seller)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payment' => ['method' => 'cash', 'amount' => 1000],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_LOGIC_ERROR');

        $this->assertSame(0, Sale::count());
        $this->assertSame('2.00', (string) $product->refresh()->stock); // untouched
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_credit_sale_respects_customer_credit_limit(): void
    {
        [$business, ] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'credit_limit' => 100.00,
            'current_balance' => 90.00,
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'sale_price' => 50.00,
            'tax_rate' => 0,
            'stock' => 10,
            'track_stock' => true,
        ]);

        // 90 owed + 50 new > 100 limit → rejected.
        $this->actingAsApi($seller)->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'pay_later' => true,
        ])->assertStatus(422)->assertJsonPath('error.code', 'BUSINESS_LOGIC_ERROR');

        $this->assertSame('90.00', (string) $customer->refresh()->current_balance);
        $this->assertSame(0, Sale::count());
    }

    public function test_credit_sale_within_limit_charges_customer_balance_and_awards_no_points(): void
    {
        [$business, ] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'credit_limit' => 5000.00,
            'current_balance' => 0.00,
            'loyalty_points' => 0,
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'sale_price' => 200.00,
            'tax_rate' => 0,
            'stock' => 10,
            'track_stock' => true,
        ]);

        $this->actingAsApi($seller)->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'pay_later' => true,
        ])->assertCreated();

        $this->assertSame('200.00', (string) $customer->refresh()->current_balance);
        $this->assertSame(0, $customer->loyalty_points); // points only on paid amounts
    }

    public function test_paid_sale_awards_loyalty_points(): void
    {
        [$business, ] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'loyalty_points' => 0,
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'sale_price' => 100.00,
            'tax_rate' => 0,
            'stock' => 10,
            'track_stock' => true,
        ]);

        $this->actingAsApi($seller)->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment' => ['method' => 'card', 'amount' => 100],
        ])->assertCreated();

        $this->assertSame(100, $customer->refresh()->loyalty_points); // 1 point per unit spent
    }

    public function test_owner_can_cancel_a_sale_and_stock_is_reverted(): void
    {
        [$business, $owner] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'sale_price' => 10.00,
            'tax_rate' => 0,
            'stock' => 10,
            'track_stock' => true,
        ]);

        $this->actingAsApi($seller)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
            'payment' => ['method' => 'cash', 'amount' => 40],
        ])->assertCreated();

        $this->assertSame('6.00', (string) $product->refresh()->stock);

        $sale = Sale::query()->sole();

        $this->actingAsApi($owner)->postJson("/api/v1/sales/{$sale->id}/cancel", [
            'reason' => 'Wrong items scanned',
            'restock' => true,
        ])->assertOk();

        $this->assertSame('10.00', (string) $product->refresh()->stock);
        $this->assertSame(SaleStatus::CANCELLED, $sale->refresh()->status);
        $this->assertSame(2, InventoryMovement::count()); // sale + return
    }

    public function test_seller_cannot_cancel_a_sale(): void
    {
        [$business, ] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $product = Product::factory()->create(['business_id' => $business->id, 'stock' => 10, 'tax_rate' => 0]);

        $this->actingAsApi($seller)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment' => ['method' => 'cash', 'amount' => (float) $product->sale_price],
        ])->assertCreated();

        $sale = Sale::query()->sole();

        $this->actingAsApi($seller)
            ->postJson("/api/v1/sales/{$sale->id}/cancel", ['reason' => 'oops'])
            ->assertStatus(403);
    }

    public function test_receipt_endpoint_returns_printable_payload(): void
    {
        [$business, ] = $this->makeBusinessWithOwner();
        $seller = $this->makeSeller($business);

        $product = Product::factory()->create(['business_id' => $business->id, 'stock' => 10, 'tax_rate' => 0]);

        $this->actingAsApi($seller)->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment' => ['method' => 'cash', 'amount' => (float) $product->sale_price],
        ])->assertCreated();

        $sale = Sale::query()->sole();

        $this->actingAsApi($seller)
            ->getJson("/api/v1/sales/{$sale->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.sale.reference_number', $sale->reference_number)
            ->assertJsonStructure(['data' => ['business', 'sale', 'items', 'payments', 'served_by']]);
    }
}
