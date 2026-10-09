<?php

namespace Tests\Feature;

use App\Data\PurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderImmutableException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseOrderImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public static function terminalStates(): array
    {
        return [
            'RECEIVED' => ['received', PurchaseOrderStatus::Received],
            'CANCELLED' => ['cancelled', PurchaseOrderStatus::Cancelled],
        ];
    }

    private function terminalOrder(string $state): PurchaseOrder
    {
        return PurchaseOrder::factory()->approved()->withItems(2)->{$state}()->create()->load('items');
    }

    /**
     * A snapshot of every persisted column of the order and its items.
     */
    private function snapshot(PurchaseOrder $order): array
    {
        return [
            'order' => (array) PurchaseOrder::query()->whereKey($order->id)->toBase()->first(),
            'items' => $order->items()->toBase()->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    private function replacementPayload(): array
    {
        return [
            'supplier_id' => Supplier::factory()->create()->id,
            'items' => [
                ['product_id' => Product::factory()->create()->id, 'quantity' => 99, 'unit_price' => '1.00'],
            ],
        ];
    }

    #[DataProvider('terminalStates')]
    public function test_api_update_of_a_terminal_order_is_rejected(string $state, PurchaseOrderStatus $status): void
    {
        $this->actingAsApiAdmin();
        $order = $this->terminalOrder($state);
        $before = $this->snapshot($order);

        $this->putJson("/api/purchase-orders/{$order->id}", $this->replacementPayload())
            ->assertUnprocessable()
            ->assertExactJson(['message' => "Purchase order is {$status->value} and can no longer be modified."]);

        $this->assertEquals($before, $this->snapshot($order));
    }

    #[DataProvider('terminalStates')]
    public function test_service_refuses_to_edit_a_terminal_order(string $state, PurchaseOrderStatus $status): void
    {
        $order = $this->terminalOrder($state);
        $before = $this->snapshot($order);

        try {
            app(PurchaseOrderService::class)->updateDraft($order, PurchaseOrderData::fromArray($this->replacementPayload()));
            $this->fail('Expected PurchaseOrderImmutableException.');
        } catch (PurchaseOrderImmutableException $e) {
            $this->assertStringContainsString($status->value, $e->getMessage());
        }

        $this->assertEquals($before, $this->snapshot($order));
    }

    #[DataProvider('terminalStates')]
    public function test_line_items_cannot_be_changed_added_or_removed_even_bypassing_services(string $state, PurchaseOrderStatus $status): void
    {
        $order = $this->terminalOrder($state);
        $this->assertSame($status, $order->status);
        $before = $this->snapshot($order);
        $item = $order->items->first();

        $attempts = [
            'change quantity' => fn () => $item->update(['quantity' => 500]),
            'change unit price' => fn () => $item->update(['unit_price' => '0.01']),
            'remove item' => fn () => $item->delete(),
            'add item' => fn () => $order->items()->create([
                'product_id' => Product::factory()->create()->id,
                'quantity' => 1,
                'unit_price' => '1.00',
            ]),
        ];

        foreach ($attempts as $description => $attempt) {
            try {
                $attempt();
                $this->fail("Expected '{$description}' to be rejected.");
            } catch (PurchaseOrderImmutableException) {
                // expected
            }
        }

        $this->assertEquals($before, $this->snapshot($order));
    }

    #[DataProvider('terminalStates')]
    public function test_order_header_and_status_cannot_be_changed_or_deleted(string $state, PurchaseOrderStatus $status): void
    {
        $order = $this->terminalOrder($state);
        $this->assertSame($status, $order->status);
        $before = $this->snapshot($order);
        $otherSupplier = Supplier::factory()->create();

        $attempts = [
            'change supplier' => fn () => $order->fresh()->update(['supplier_id' => $otherSupplier->id]),
            'change status' => fn () => $order->fresh()->forceFill(['status' => PurchaseOrderStatus::Draft])->save(),
            'delete order' => fn () => $order->fresh()->delete(),
        ];

        foreach ($attempts as $description => $attempt) {
            try {
                $attempt();
                $this->fail("Expected '{$description}' to be rejected.");
            } catch (PurchaseOrderImmutableException) {
                // expected
            }
        }

        $this->assertEquals($before, $this->snapshot($order));
    }

    #[DataProvider('terminalStates')]
    public function test_web_update_of_a_terminal_order_is_rejected(string $state, PurchaseOrderStatus $status): void
    {
        $order = $this->terminalOrder($state);
        $before = $this->snapshot($order);

        $this->actingAs($this->admin())
            ->from(route('purchase-orders.show', $order))
            ->put(route('purchase-orders.update', $order), $this->replacementPayload())
            ->assertRedirect(route('purchase-orders.show', $order))
            ->assertSessionHas('error', "Purchase order is {$status->value} and can no longer be modified.");

        $this->assertEquals($before, $this->snapshot($order));
    }

    public function test_approved_orders_are_locked_for_editing(): void
    {
        $this->actingAsApiAdmin();
        $order = PurchaseOrder::factory()->approved()->withItems(1)->create()->load('items');
        $before = $this->snapshot($order);

        $this->putJson("/api/purchase-orders/{$order->id}", $this->replacementPayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only DRAFT purchase orders can be edited; this purchase order is APPROVED.');

        $this->assertEquals($before, $this->snapshot($order));
    }

    public function test_draft_orders_can_be_edited_and_totals_are_recomputed(): void
    {
        $this->actingAsApiAdmin();
        $order = PurchaseOrder::factory()->withItems(2)->create();
        $payload = $this->replacementPayload();

        $this->putJson("/api/purchase-orders/{$order->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.supplier.id', $payload['supplier_id'])
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.total', '99.00');

        $this->assertDatabaseCount('purchase_order_items', 1);
    }
}
