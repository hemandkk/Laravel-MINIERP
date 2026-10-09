<?php

namespace Tests\Feature\Api;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private function patchStatus(PurchaseOrder $order, string $status)
    {
        return $this->patchJson("/api/purchase-orders/{$order->id}/status", ['status' => $status]);
    }

    public function test_receiving_an_approved_order_increments_stock(): void
    {
        $this->actingAsApiAdmin();
        $productA = Product::factory()->withStock(10)->create();
        $order = $this->approvedOrderFor($productA, 5);

        $this->patchStatus($order, 'RECEIVED')
            ->assertOk()
            ->assertJsonPath('message', 'Purchase order status updated to RECEIVED.')
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('data.allowed_transitions', [])
            ->assertJsonPath('data.total', '50.00');

        $order->refresh();
        $this->assertSame(15, $productA->fresh()->stock_quantity);
        $this->assertSame(PurchaseOrderStatus::Received, $order->status);
        $this->assertNotNull($order->received_at);
    }

    public function test_receiving_twice_increments_stock_only_once(): void
    {
        $this->actingAsApiAdmin();
        $product = Product::factory()->withStock(10)->create();
        $order = $this->approvedOrderFor($product, 5);

        $this->patchStatus($order, 'RECEIVED')->assertOk();
        $receivedAt = $order->fresh()->received_at;

        $this->patchStatus($order, 'RECEIVED')
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'Purchase order cannot transition from RECEIVED to RECEIVED.']);

        $this->assertSame(15, $product->fresh()->stock_quantity);
        $this->assertEquals($receivedAt, $order->fresh()->received_at);
    }

    public function test_received_to_approved_is_rejected_without_any_mutation(): void
    {
        $this->actingAsApiAdmin();
        $product = Product::factory()->withStock(10)->create();
        $order = $this->approvedOrderFor($product, 5);
        $this->patchStatus($order, 'RECEIVED')->assertOk();

        $orderBefore = PurchaseOrder::query()->whereKey($order->id)->toBase()->first();
        $productBefore = Product::query()->whereKey($product->id)->toBase()->first();

        $this->patchStatus($order, 'APPROVED')
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'Purchase order cannot transition from RECEIVED to APPROVED.']);

        $this->assertEquals($orderBefore, PurchaseOrder::query()->whereKey($order->id)->toBase()->first());
        $this->assertEquals($productBefore, Product::query()->whereKey($product->id)->toBase()->first());
    }

    public function test_draft_cannot_be_received_directly(): void
    {
        $this->actingAsApiAdmin();
        $product = Product::factory()->withStock(10)->create();
        $order = PurchaseOrder::factory()->create();
        $order->items()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => '1.00']);

        $this->patchStatus($order, 'RECEIVED')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Purchase order cannot transition from DRAFT to RECEIVED.');

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(PurchaseOrderStatus::Draft, $order->fresh()->status);
    }

    public function test_approving_a_draft_sets_approved_at_without_touching_stock(): void
    {
        $this->actingAsApiAdmin();
        $product = Product::factory()->withStock(10)->create();
        $order = PurchaseOrder::factory()->create();
        $order->items()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => '1.00']);

        $this->patchStatus($order, 'APPROVED')
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertNotNull($order->fresh()->approved_at);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_cancelling_sets_cancelled_at_without_touching_stock(): void
    {
        $this->actingAsApiAdmin();
        $product = Product::factory()->withStock(10)->create();
        $approved = $this->approvedOrderFor($product, 5);
        $draft = PurchaseOrder::factory()->create();

        $this->patchStatus($approved, 'CANCELLED')->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->patchStatus($draft, 'CANCELLED')->assertOk()->assertJsonPath('data.status', 'CANCELLED');

        $this->assertNotNull($approved->fresh()->cancelled_at);
        $this->assertNotNull($draft->fresh()->cancelled_at);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_cancelled_order_cannot_be_approved_or_received(): void
    {
        $this->actingAsApiAdmin();
        $order = PurchaseOrder::factory()->withItems(1)->cancelled()->create();

        $this->patchStatus($order, 'APPROVED')->assertUnprocessable();
        $this->patchStatus($order, 'RECEIVED')->assertUnprocessable();

        $this->assertSame(PurchaseOrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_draft_is_not_an_accepted_target_status(): void
    {
        $this->actingAsApiAdmin();
        $order = PurchaseOrder::factory()->approved()->create();

        $this->patchStatus($order, 'DRAFT')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->patchJson("/api/purchase-orders/{$order->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->patchStatus($order, 'SHIPPED')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_unauthenticated_status_update_returns_401(): void
    {
        $product = Product::factory()->withStock(10)->create();
        $order = $this->approvedOrderFor($product, 5);

        $this->patchStatus($order, 'RECEIVED')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->assertSame(PurchaseOrderStatus::Approved, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_non_admin_status_update_returns_403(): void
    {
        Sanctum::actingAs($this->viewer());
        $product = Product::factory()->withStock(10)->create();
        $order = $this->approvedOrderFor($product, 5);

        $this->patchStatus($order, 'RECEIVED')->assertForbidden();

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_unknown_purchase_order_returns_404(): void
    {
        $this->actingAsApiAdmin();

        $this->patchJson('/api/purchase-orders/999999/status', ['status' => 'APPROVED'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }
}
