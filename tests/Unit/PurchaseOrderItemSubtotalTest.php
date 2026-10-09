<?php

namespace Tests\Unit;

use App\Data\PurchaseOrderLineData;
use App\Models\PurchaseOrderItem;
use Tests\TestCase;

class PurchaseOrderItemSubtotalTest extends TestCase
{
    public function test_line_subtotal_is_quantity_times_unit_price(): void
    {
        $item = new PurchaseOrderItem(['quantity' => 5, 'unit_price' => '10.50']);

        $this->assertSame('52.50', $item->subtotal());
    }

    public function test_line_data_subtotal_matches_model_subtotal(): void
    {
        $line = new PurchaseOrderLineData(productId: 1, quantity: 5, unitPrice: '10.50');

        $this->assertSame('52.50', $line->subtotal());
    }

    public function test_zero_unit_price_yields_zero_subtotal(): void
    {
        $item = new PurchaseOrderItem(['quantity' => 7, 'unit_price' => '0']);

        $this->assertSame('0.00', $item->subtotal());
    }
}
