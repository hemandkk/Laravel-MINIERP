<?php

namespace Tests\Unit;

use App\Data\PurchaseOrderData;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class PurchaseOrderTotalTest extends TestCase
{
    public function test_grand_total_is_the_sum_of_line_subtotals(): void
    {
        $order = new PurchaseOrder;
        $order->setRelation('items', new Collection([
            new PurchaseOrderItem(['quantity' => 5, 'unit_price' => '10.00']), // 50.00
            new PurchaseOrderItem(['quantity' => 2, 'unit_price' => '25.00']), // 50.00
        ]));

        $this->assertSame('100.00', $order->grandTotal());
    }

    public function test_grand_total_prefers_the_sql_aggregate_when_present(): void
    {
        $order = (new PurchaseOrder)->setRawAttributes(['id' => 1, 'total_amount' => '100.0000']);

        $this->assertSame('100.00', $order->grandTotal());
    }

    public function test_purchase_order_data_total_is_derived_from_lines(): void
    {
        $data = PurchaseOrderData::fromArray([
            'supplier_id' => 1,
            'items' => [
                ['product_id' => 1, 'quantity' => 5, 'unit_price' => '10.00'],
                ['product_id' => 2, 'quantity' => 2, 'unit_price' => '25.00'],
            ],
        ]);

        $this->assertSame('100.00', $data->total());
    }
}
