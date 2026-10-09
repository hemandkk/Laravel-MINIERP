<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_are_computed_with_aggregates(): void
    {
        Product::factory()->count(3)->create(['stock_quantity' => 50, 'low_stock_threshold' => 10]);
        Product::factory()->count(2)->lowStock()->create();
        Product::factory()->inactive()->lowStock()->create();

        $received = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory()->state(['quantity' => 5, 'unit_price' => '10.00']), 'items')
            ->has(PurchaseOrderItem::factory()->state(['quantity' => 2, 'unit_price' => '25.00']), 'items')
            ->received()
            ->create();
        PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory()->state(['quantity' => 100, 'unit_price' => '100.00']), 'items')
            ->approved()
            ->create();

        $metrics = app(DashboardService::class)->metrics();

        // Line-item factories create their own (active, non-low) products: 3 + 2 + 3 = 8 active.
        $this->assertSame(Product::query()->where('is_active', true)->count(), $metrics['active_products']);
        $this->assertSame(2, $metrics['low_stock_products']);
        $this->assertSame('100.00', $metrics['total_expenditure']);
        $this->assertSame($received->grandTotal(), $metrics['total_expenditure']);
    }

    public function test_dashboard_page_shows_metrics_and_latest_five_orders(): void
    {
        $orders = PurchaseOrder::factory()->count(6)->withItems(1)->sequence(
            fn ($sequence) => ['order_date' => now()->subDays(10 - $sequence->index)->toDateString()],
        )->create();

        $response = $this->actingAs($this->viewer())->get('/dashboard');

        $response->assertOk()
            ->assertSee('Total Active Products')
            ->assertSee('Low Stock Alerts')
            ->assertSee('Total Expenditure')
            ->assertSee('badge text-bg-secondary', false);

        // The oldest order is not among the latest five.
        $response->assertDontSee($orders->first()->number);
        foreach ($orders->slice(1) as $order) {
            $response->assertSee($order->number);
        }
    }
}
