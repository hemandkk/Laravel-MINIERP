<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guards against N+1 regressions: the number of queries per page must not grow
 * with the number of rows. (Lazy loading is also disabled outside production,
 * so an accidental N+1 throws immediately.)
 */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_web_pages_run_a_constant_number_of_queries(): void
    {
        $user = $this->admin();

        PurchaseOrder::factory()->count(2)->withItems(2)->create();
        $few = [
            'dashboard' => $this->countQueries(fn () => $this->actingAs($user)->get('/dashboard')->assertOk()),
            'po index' => $this->countQueries(fn () => $this->actingAs($user)->get('/purchase-orders')->assertOk()),
            'inventory' => $this->countQueries(fn () => $this->actingAs($user)->get('/inventory')->assertOk()),
        ];

        PurchaseOrder::factory()->count(12)->withItems(3)->create();
        $many = [
            'dashboard' => $this->countQueries(fn () => $this->actingAs($user)->get('/dashboard')->assertOk()),
            'po index' => $this->countQueries(fn () => $this->actingAs($user)->get('/purchase-orders')->assertOk()),
            'inventory' => $this->countQueries(fn () => $this->actingAs($user)->get('/inventory')->assertOk()),
        ];

        $this->assertSame($few, $many);
    }

    public function test_purchase_order_detail_query_count_does_not_grow_with_lines(): void
    {
        $user = $this->admin();
        $small = PurchaseOrder::factory()->withItems(1)->create();
        $large = PurchaseOrder::factory()->withItems(15)->create();

        $this->assertSame(
            $this->countQueries(fn () => $this->actingAs($user)->get(route('purchase-orders.show', $small))->assertOk()),
            $this->countQueries(fn () => $this->actingAs($user)->get(route('purchase-orders.show', $large))->assertOk()),
        );
    }

    public function test_api_inventory_and_report_run_a_constant_number_of_queries(): void
    {
        Sanctum::actingAs($this->viewer());

        Product::factory()->count(3)->create();
        PurchaseOrder::factory()->withItems(2)->received()->create();
        $few = [
            $this->countQueries(fn () => $this->getJson('/api/inventory')->assertOk()),
            $this->countQueries(fn () => $this->getJson('/api/reports/supplier-spend')->assertOk()),
        ];

        Product::factory()->count(15)->create();
        PurchaseOrder::factory()->count(5)->withItems(3)->received()->create();
        $many = [
            $this->countQueries(fn () => $this->getJson('/api/inventory')->assertOk()),
            $this->countQueries(fn () => $this->getJson('/api/reports/supplier-spend')->assertOk()),
        ];

        $this->assertSame($few, $many);
    }
}
