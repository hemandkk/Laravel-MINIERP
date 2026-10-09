<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Queries\SupplierSpendReport;
use App\Services\DashboardService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierSpendReportTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(Supplier $supplier, string $state, array $lines): PurchaseOrder
    {
        $factory = PurchaseOrder::factory()->for($supplier);
        $factory = match ($state) {
            'approved' => $factory->approved(),
            'received' => $factory->received(),
            'cancelled' => $factory->cancelled(),
            default => $factory,
        };

        foreach ($lines as [$quantity, $price]) {
            $factory = $factory->has(
                PurchaseOrderItem::factory()->state([
                    'product_id' => Product::factory(),
                    'quantity' => $quantity,
                    'unit_price' => $price,
                ]),
                'items',
            );
        }

        return $factory->create();
    }

    public function test_report_requires_authentication(): void
    {
        $this->getJson('/api/reports/supplier-spend')->assertUnauthorized();
    }

    public function test_report_aggregates_only_received_orders_per_supplier(): void
    {
        Sanctum::actingAs($this->viewer());

        $alpha = Supplier::factory()->create(['name' => 'Alpha']);
        $beta = Supplier::factory()->create(['name' => 'Beta']);
        $gamma = Supplier::factory()->create(['name' => 'Gamma']);

        // Alpha: 2 received orders = 50.00 + 100.00 + 25.00 = 175.00
        $this->orderFor($alpha, 'received', [[5, '10.00'], [4, '25.00']]);
        $this->orderFor($alpha, 'received', [[1, '25.00']]);
        // Beta: 1 received order = 300.00 (should rank first)
        $this->orderFor($beta, 'received', [[3, '100.00']]);
        // Non-received orders are excluded entirely.
        $this->orderFor($alpha, 'draft', [[100, '100.00']]);
        $this->orderFor($beta, 'approved', [[100, '100.00']]);
        $this->orderFor($gamma, 'cancelled', [[100, '100.00']]);

        $this->getJson('/api/reports/supplier-spend')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.supplier_id', $beta->id)
            ->assertJsonPath('data.0.supplier_name', 'Beta')
            ->assertJsonPath('data.0.total_spend', '300.00')
            ->assertJsonPath('data.0.received_orders_count', 1)
            ->assertJsonPath('data.1.supplier_id', $alpha->id)
            ->assertJsonPath('data.1.total_spend', '175.00')
            ->assertJsonPath('data.1.received_orders_count', 2)
            ->assertJsonPath('meta.grand_total', '475.00')
            ->assertJsonPath('meta.suppliers_count', 2);
    }

    public function test_report_is_a_single_aggregate_query_consistent_with_other_totals(): void
    {
        $supplier = Supplier::factory()->create();
        $first = $this->orderFor($supplier, 'received', [[3, '19.99'], [7, '0.35']]);
        $second = $this->orderFor($supplier, 'received', [[1, '1000.01']]);

        DB::enableQueryLog();
        $rows = app(SupplierSpendReport::class)->get();
        $this->assertCount(1, DB::getQueryLog(), 'Supplier spend must be computed by one SQL aggregate.');
        DB::disableQueryLog();

        $expected = Money::sum([$first->grandTotal(), $second->grandTotal()]);

        $this->assertSame('1062.43', $expected);
        $this->assertSame($expected, Money::normalize($rows->sole()->total_spend));
        $this->assertSame($expected, app(DashboardService::class)->totalExpenditure());
    }
}
