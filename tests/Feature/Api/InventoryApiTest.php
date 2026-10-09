<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_requires_authentication(): void
    {
        $this->getJson('/api/inventory')->assertUnauthorized();
        $this->getJson('/api/inventory/low-stock')->assertUnauthorized();
    }

    public function test_inventory_is_paginated_and_exposes_low_stock_flag(): void
    {
        Sanctum::actingAs($this->viewer());

        Product::factory()->create(['sku' => 'SKU-001', 'name' => 'Product A', 'unit_price' => '49.99', 'stock_quantity' => 25]);
        Product::factory()->create(['sku' => 'SKU-002', 'name' => 'Product B', 'stock_quantity' => 2, 'low_stock_threshold' => 10]);

        $response = $this->getJson('/api/inventory?per_page=1');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [['id', 'sku', 'name', 'stock_quantity', 'unit_price', 'low_stock', 'low_stock_threshold', 'is_active']],
                'links',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.sku', 'SKU-001')
            ->assertJsonPath('data.0.stock_quantity', 25)
            ->assertJsonPath('data.0.unit_price', '49.99')
            ->assertJsonPath('data.0.low_stock', false);

        $this->getJson('/api/inventory?page=2&per_page=1')
            ->assertJsonPath('data.0.sku', 'SKU-002')
            ->assertJsonPath('data.0.low_stock', true);
    }

    public function test_low_stock_endpoint_returns_only_active_products_below_threshold(): void
    {
        Sanctum::actingAs($this->viewer());

        $low = Product::factory()->create(['stock_quantity' => 3, 'low_stock_threshold' => 10]);
        $atThreshold = Product::factory()->create(['stock_quantity' => 10, 'low_stock_threshold' => 10]);
        $healthy = Product::factory()->create(['stock_quantity' => 50, 'low_stock_threshold' => 10]);
        $inactiveLow = Product::factory()->inactive()->create(['stock_quantity' => 1, 'low_stock_threshold' => 10]);

        $response = $this->getJson('/api/inventory/low-stock')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertEquals([$low->id], $ids->all());
        $this->assertNotContains($atThreshold->id, $ids, 'stock == threshold is not "low" (strictly less than).');
        $this->assertNotContains($healthy->id, $ids);
        $this->assertNotContains($inactiveLow->id, $ids);
        $response->assertJsonPath('data.0.low_stock', true);
    }

    public function test_inventory_search_matches_sku_or_name_prefix(): void
    {
        Sanctum::actingAs($this->viewer());

        Product::factory()->create(['sku' => 'ELEC-1', 'name' => 'Mouse']);
        Product::factory()->create(['sku' => 'OFF-1', 'name' => 'Electric Stapler']);
        Product::factory()->create(['sku' => 'OFF-2', 'name' => 'Paper']);

        $this->getJson('/api/inventory?search=ele')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_invalid_filters_return_422(): void
    {
        Sanctum::actingAs($this->viewer());

        $this->getJson('/api/inventory?per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }
}
