<?php

namespace Tests\Feature\Web;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_lists_suppliers_and_active_products(): void
    {
        Supplier::factory()->create(['name' => 'Acme Supplies']);
        Product::factory()->create(['name' => 'Active Widget']);
        Product::factory()->inactive()->create(['name' => 'Retired Widget']);

        $this->actingAs($this->admin())
            ->get('/purchase-orders/create')
            ->assertOk()
            ->assertSee('Acme Supplies')
            ->assertSee('Active Widget')
            ->assertDontSee('Retired Widget')
            ->assertSee('id="line-item-template"', false)
            ->assertSee('js/purchase-order-form.js');
    }

    public function test_store_creates_a_draft_and_ignores_client_totals(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->withStock(5)->create();

        $response = $this->actingAs($this->admin())->post('/purchase-orders', [
            'supplier_id' => $supplier->id,
            'grand_total' => '1.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4, 'unit_price' => '12.25', 'subtotal' => '1.00'],
            ],
        ]);

        $order = PurchaseOrder::query()->sole();
        $response->assertRedirect(route('purchase-orders.show', $order))->assertSessionHas('success');

        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertSame('49.00', $order->grandTotal());
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_store_with_invalid_lines_returns_errors(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->admin())
            ->from('/purchase-orders/create')
            ->post('/purchase-orders', [
                'supplier_id' => $supplier->id,
                'items' => [['product_id' => '', 'quantity' => 0, 'unit_price' => '-1']],
            ])
            ->assertRedirect('/purchase-orders/create')
            ->assertSessionHasErrors(['items.0.product_id', 'items.0.quantity', 'items.0.unit_price']);

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_show_page_displays_lines_and_state_dependent_actions(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['sku' => 'SKU-777', 'name' => 'Gizmo']);
        $draft = PurchaseOrder::factory()->create();
        $draft->items()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => '10.50']);

        $this->actingAs($admin)->get(route('purchase-orders.show', $draft))
            ->assertOk()
            ->assertSee('SKU-777')
            ->assertSee('Gizmo')
            ->assertSee('52.50')
            ->assertSee('Approve')
            ->assertSee('Cancel Order')
            ->assertDontSee('Mark as Received');

        $approved = PurchaseOrder::factory()->approved()->withItems(1)->create();
        $this->actingAs($admin)->get(route('purchase-orders.show', $approved))
            ->assertSee('Mark as Received')
            ->assertSee('Cancel Order')
            ->assertDontSee('>Approve<', false);

        $received = PurchaseOrder::factory()->withItems(1)->received()->create();
        $this->actingAs($admin)->get(route('purchase-orders.show', $received))
            ->assertDontSee('Mark as Received')
            ->assertDontSee('Cancel Order')
            ->assertSee('can no longer be modified');
    }

    public function test_lifecycle_actions_via_patch(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->withStock(10)->create();
        $order = PurchaseOrder::factory()->create();
        $order->items()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => '2.00']);

        $this->actingAs($admin)->patch(route('purchase-orders.approve', $order))
            ->assertRedirect(route('purchase-orders.show', $order))
            ->assertSessionHas('success');
        $this->assertSame(PurchaseOrderStatus::Approved, $order->fresh()->status);

        $this->actingAs($admin)->patch(route('purchase-orders.receive', $order))->assertSessionHas('success');
        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
        $this->assertSame(15, $product->fresh()->stock_quantity);

        // Hidden buttons are not the protection: a forged request is rejected by the domain layer.
        $this->actingAs($admin)
            ->from(route('purchase-orders.show', $order))
            ->patch(route('purchase-orders.cancel', $order))
            ->assertRedirect(route('purchase-orders.show', $order))
            ->assertSessionHas('error', 'Purchase order cannot transition from RECEIVED to CANCELLED.');
        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
    }

    public function test_state_changes_cannot_be_triggered_with_get(): void
    {
        $order = PurchaseOrder::factory()->withItems(1)->create();

        $this->actingAs($this->admin())
            ->get("/purchase-orders/{$order->id}/approve")
            ->assertMethodNotAllowed();

        $this->assertSame(PurchaseOrderStatus::Draft, $order->fresh()->status);
    }

    public function test_viewers_cannot_create_or_transition_orders(): void
    {
        $viewer = $this->viewer();
        $order = PurchaseOrder::factory()->withItems(1)->create();

        $this->actingAs($viewer)->get('/purchase-orders/create')->assertForbidden();
        $this->actingAs($viewer)->patch(route('purchase-orders.approve', $order))->assertForbidden();
        $this->actingAs($viewer)->get(route('purchase-orders.show', $order))
            ->assertOk()
            ->assertSee('read-only access');

        $this->assertSame(PurchaseOrderStatus::Draft, $order->fresh()->status);
    }

    public function test_draft_can_be_edited_but_received_order_edit_page_redirects(): void
    {
        $admin = $this->admin();
        $draft = PurchaseOrder::factory()->withItems(2)->create();
        $received = PurchaseOrder::factory()->withItems(1)->received()->create();

        $this->actingAs($admin)->get(route('purchase-orders.edit', $draft))
            ->assertOk()
            ->assertSee('Save Changes');

        $this->actingAs($admin)->get(route('purchase-orders.edit', $received))
            ->assertRedirect(route('purchase-orders.show', $received))
            ->assertSessionHas('error');
    }

    public function test_index_filters_by_status(): void
    {
        $draft = PurchaseOrder::factory()->withItems(1)->create();
        $cancelled = PurchaseOrder::factory()->withItems(1)->cancelled()->create();

        $this->actingAs($this->viewer())
            ->get('/purchase-orders?status=CANCELLED')
            ->assertOk()
            ->assertSee($cancelled->number)
            ->assertDontSee($draft->number);
    }
}
