<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderShowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_details_include_items_and_server_total(): void
    {
        Sanctum::actingAs($this->viewer());
        $order = PurchaseOrder::factory()->create();
        $order->items()->createMany([
            ['product_id' => Product::factory()->create()->id, 'quantity' => 5, 'unit_price' => '10.00'],
            ['product_id' => Product::factory()->create()->id, 'quantity' => 2, 'unit_price' => '25.00'],
        ]);

        $this->getJson("/api/purchase-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.number', $order->number)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonStructure(['data' => ['items' => [['product' => ['id', 'sku', 'name'], 'quantity', 'unit_price', 'subtotal']]]])
            ->assertJsonPath('data.total', '100.00');
    }

    public function test_unknown_endpoint_returns_json_404(): void
    {
        Sanctum::actingAs($this->viewer());

        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'The requested endpoint does not exist.']);
    }
}
