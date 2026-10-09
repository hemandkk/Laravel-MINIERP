<?php

namespace Tests\Feature;

use App\Actions\ReceivePurchaseOrderAction;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderStatusService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivePurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_increments_stock_for_every_line(): void
    {
        $productA = Product::factory()->withStock(10)->create();
        $productB = Product::factory()->withStock(0)->create();
        $order = PurchaseOrder::factory()->approved()->create();
        $order->items()->createMany([
            ['product_id' => $productA->id, 'quantity' => 5, 'unit_price' => '10.50'],
            ['product_id' => $productB->id, 'quantity' => 12, 'unit_price' => '3.00'],
        ]);

        $received = app(ReceivePurchaseOrderAction::class)->execute($order);

        $this->assertSame(PurchaseOrderStatus::Received, $received->status);
        $this->assertNotNull($received->received_at);
        $this->assertSame(15, $productA->fresh()->stock_quantity);
        $this->assertSame(12, $productB->fresh()->stock_quantity);
    }

    public function test_a_stale_model_cannot_receive_an_already_received_order(): void
    {
        $product = Product::factory()->withStock(10)->create();
        $order = $this->approvedOrderFor($product, 5);

        // Two "requests" both loaded the order while it was APPROVED.
        $requestA = PurchaseOrder::query()->findOrFail($order->id);
        $requestB = PurchaseOrder::query()->findOrFail($order->id);

        app(PurchaseOrderStatusService::class)->receive($requestA);

        $this->assertSame(PurchaseOrderStatus::Approved, $requestB->status, 'precondition: B holds a stale status');

        try {
            app(PurchaseOrderStatusService::class)->receive($requestB);
            $this->fail('Expected the second receive to be rejected.');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame('Purchase order cannot transition from RECEIVED to RECEIVED.', $e->getMessage());
        }

        $this->assertSame(15, $product->fresh()->stock_quantity);
    }

    public function test_failure_part_way_through_rolls_back_stock_and_status(): void
    {
        $first = Product::factory()->withStock(10)->create();
        // The UNSIGNED INT column cannot exceed 4294967295, so incrementing this product fails
        // after the first product has already been incremented inside the transaction.
        $second = Product::factory()->withStock(4_294_967_295)->create();

        $order = PurchaseOrder::factory()->approved()->create();
        $order->items()->createMany([
            ['product_id' => $first->id, 'quantity' => 5, 'unit_price' => '1.00'],
            ['product_id' => $second->id, 'quantity' => 1, 'unit_price' => '1.00'],
        ]);

        try {
            app(ReceivePurchaseOrderAction::class)->execute($order);
            $this->fail('Expected the receive to fail.');
        } catch (QueryException) {
            // expected
        }

        $order->refresh();
        $this->assertSame(10, $first->fresh()->stock_quantity, 'first increment must be rolled back');
        $this->assertSame(4_294_967_295, $second->fresh()->stock_quantity);
        $this->assertSame(PurchaseOrderStatus::Approved, $order->status);
        $this->assertNull($order->received_at);
    }

    public function test_approving_and_cancelling_never_change_stock(): void
    {
        $product = Product::factory()->withStock(10)->create();
        $toCancel = $this->approvedOrderFor($product, 5);
        $draft = PurchaseOrder::factory()->create();
        $draft->items()->create(['product_id' => $product->id, 'quantity' => 7, 'unit_price' => '1.00']);

        $service = app(PurchaseOrderStatusService::class);
        $service->approve($draft);
        $service->cancel($toCancel);

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }
}
