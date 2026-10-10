<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Response envelope contract + public endpoints + health check. */
class ApiEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure(['meta' => ['timestamp', 'request_id']]);
    }

    public function test_every_response_carries_the_request_id_header_and_meta(): void
    {
        $response = $this->getJson('/api/health');

        $requestId = $response->headers->get('X-Request-Id');
        $this->assertNotNull($requestId);
        $this->assertSame($requestId, $response->json('meta.request_id'));
    }

    public function test_unknown_endpoint_returns_standard_404_envelope(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonStructure(['meta' => ['timestamp', 'request_id']]);
    }

    public function test_public_plans_endpoint_lists_active_plans(): void
    {
        \App\Models\Plan::factory()->create(['is_active' => true, 'name' => 'Starter']);
        \App\Models\Plan::factory()->create(['is_active' => false, 'name' => 'Hidden']);

        $response = $this->getJson('/api/v1/plans');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Starter', $response->json('data.0.name'));
    }

    public function test_public_business_types_endpoint(): void
    {
        $this->getJson('/api/v1/business-types')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.value', 'store');
    }

    public function test_products_index_is_paginated_with_meta(): void
    {
        [$business, $owner] = $this->makeBusinessWithOwner();
        Product::factory()->count(20)->create(['business_id' => $business->id]);

        $response = $this->actingAsApi($owner)->getJson('/api/v1/products?per_page=5&page=2');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.total', 20)
            ->assertJsonPath('meta.pagination.has_more_pages', true);
    }

    public function test_product_crud_happy_path(): void
    {
        Mail::fake();
        [$business, $owner] = $this->makeBusinessWithOwner();

        // Create
        $create = $this->actingAsApi($owner)->postJson('/api/v1/products', [
            'name' => 'Test Widget',
            'sku' => 'WID-001',
            'cost_price' => 10,
            'sale_price' => 25.5,
            'tax_rate' => 16,
            'stock' => 100,
            'min_stock' => 10,
        ]);
        $create->assertCreated()->assertJsonPath('data.sku', 'WID-001');
        $id = $create->json('data.id');

        // Duplicated SKU within the tenant → caught by the FormRequest unique rule
        $this->actingAsApi($owner)->postJson('/api/v1/products', [
            'name' => 'Another Widget',
            'sku' => 'WID-001',
            'cost_price' => 10,
            'sale_price' => 20,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['sku']);

        // Read
        $this->actingAsApi($owner)->getJson("/api/v1/products/{$id}")
            ->assertOk()->assertJsonPath('data.name', 'Test Widget');

        // Update
        $this->actingAsApi($owner)->putJson("/api/v1/products/{$id}", ['sale_price' => 29.99])
            ->assertOk()->assertJsonPath('data.sale_price', 29.99);

        // Soft delete
        $this->actingAsApi($owner)->deleteJson("/api/v1/products/{$id}")
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSoftDeleted('products', ['id' => $id]);
    }

    public function test_audit_logs_are_written_for_product_changes(): void
    {
        [$business, $owner] = $this->makeBusinessWithOwner();

        $this->actingAsApi($owner)->postJson('/api/v1/products', [
            'name' => 'Audited Product',
            'sku' => 'AUD-001',
            'cost_price' => 5,
            'sale_price' => 12,
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'model_type' => Product::class,
            'user_id' => $owner->id,
            'business_id' => $business->id,
        ]);
    }
}
