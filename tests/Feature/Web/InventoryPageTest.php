<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_lists_products_and_highlights_low_stock(): void
    {
        Product::factory()->create(['sku' => 'OK-1', 'name' => 'Healthy Item', 'stock_quantity' => 40]);
        Product::factory()->create(['sku' => 'LOW-1', 'name' => 'Scarce Item', 'stock_quantity' => 2, 'low_stock_threshold' => 10]);

        $this->actingAs($this->viewer())
            ->get('/inventory')
            ->assertOk()
            ->assertSee('OK-1')
            ->assertSee('LOW-1')
            ->assertSee('table-danger', false)
            ->assertSee('Low stock');
    }

    public function test_low_stock_filter(): void
    {
        Product::factory()->create(['sku' => 'OK-1', 'stock_quantity' => 40]);
        Product::factory()->create(['sku' => 'LOW-1', 'stock_quantity' => 2, 'low_stock_threshold' => 10]);

        $this->actingAs($this->viewer())
            ->get('/inventory?low_stock=1')
            ->assertOk()
            ->assertSee('LOW-1')
            ->assertDontSee('OK-1');
    }
}
