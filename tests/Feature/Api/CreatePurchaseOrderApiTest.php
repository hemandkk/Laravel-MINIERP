<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreatePurchaseOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create();
        $this->productA = Product::factory()->withStock(20)->create();
        $this->productB = Product::factory()->withStock(7)->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $this->supplier->id,
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 5, 'unit_price' => '25.50'],
                ['product_id' => $this->productB->id, 'quantity' => 10, 'unit_price' => '15.00'],
            ],
        ], $overrides);
    }

    public function test_admin_can_create_a_draft_purchase_order(): void
    {
        $admin = $this->actingAsApiAdmin();

        $response = $this->postJson('/api/purchase-orders', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('message', 'Purchase order created successfully.')
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.supplier.id', $this->supplier->id)
            ->assertJsonPath('data.items.0.subtotal', '127.50')
            ->assertJsonPath('data.items.1.subtotal', '150.00')
            ->assertJsonPath('data.total', '277.50')
            ->assertJsonPath('data.allowed_transitions', ['APPROVED', 'CANCELLED']);

        $order = PurchaseOrder::query()->sole();
        $this->assertSame($admin->id, $order->created_by);
        $this->assertNull($order->approved_at);
        $this->assertDatabaseCount('purchase_order_items', 2);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 5,
            'unit_price' => '25.50',
        ]);
    }

    public function test_creating_a_purchase_order_does_not_change_stock(): void
    {
        $this->actingAsApiAdmin();

        $this->postJson('/api/purchase-orders', $this->validPayload())->assertCreated();

        $this->assertSame(20, $this->productA->fresh()->stock_quantity);
        $this->assertSame(7, $this->productB->fresh()->stock_quantity);
    }

    public function test_client_supplied_totals_and_status_are_ignored(): void
    {
        $this->actingAsApiAdmin();

        $payload = $this->validPayload([
            'status' => 'RECEIVED',
            'total' => '0.01',
            'grand_total' => '0.01',
        ]);
        $payload['items'][0]['subtotal'] = '999999.00';

        $this->postJson('/api/purchase-orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.total', '277.50')
            ->assertJsonPath('data.items.0.subtotal', '127.50');
    }

    public function test_unauthenticated_creation_returns_401(): void
    {
        $this->postJson('/api/purchase-orders', $this->validPayload())
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_non_admin_users_receive_403(): void
    {
        Sanctum::actingAs($this->viewer());

        $this->postJson('/api/purchase-orders', $this->validPayload())->assertForbidden();

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_return_422_with_error_keys(callable $mutate, array $errorKeys): void
    {
        $this->actingAsApiAdmin();

        $payload = $mutate($this->validPayload(), $this);

        $this->postJson('/api/purchase-orders', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors($errorKeys);

        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('purchase_order_items', 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing supplier_id' => [
                fn (array $p) => array_diff_key($p, ['supplier_id' => true]),
                ['supplier_id'],
            ],
            'unknown supplier' => [
                fn (array $p) => ['supplier_id' => 999_999] + $p,
                ['supplier_id'],
            ],
            'missing items' => [
                fn (array $p) => array_diff_key($p, ['items' => true]),
                ['items'],
            ],
            'empty items array' => [
                fn (array $p) => ['items' => []] + $p,
                ['items'],
            ],
            'items not an array' => [
                fn (array $p) => ['items' => 'abc'] + $p,
                ['items'],
            ],
            'zero quantity' => [
                function (array $p) {
                    $p['items'][0]['quantity'] = 0;

                    return $p;
                },
                ['items.0.quantity'],
            ],
            'negative quantity' => [
                function (array $p) {
                    $p['items'][1]['quantity'] = -3;

                    return $p;
                },
                ['items.1.quantity'],
            ],
            'fractional quantity' => [
                function (array $p) {
                    $p['items'][0]['quantity'] = 1.5;

                    return $p;
                },
                ['items.0.quantity'],
            ],
            'negative unit price' => [
                function (array $p) {
                    $p['items'][0]['unit_price'] = '-1.00';

                    return $p;
                },
                ['items.0.unit_price'],
            ],
            'unit price with three decimals' => [
                function (array $p) {
                    $p['items'][0]['unit_price'] = '1.999';

                    return $p;
                },
                ['items.0.unit_price'],
            ],
            'missing product_id' => [
                function (array $p) {
                    unset($p['items'][0]['product_id']);

                    return $p;
                },
                ['items.0.product_id'],
            ],
            'unknown product' => [
                function (array $p) {
                    $p['items'][1]['product_id'] = 999_999;

                    return $p;
                },
                ['items.1.product_id'],
            ],
            'duplicate product' => [
                function (array $p) {
                    $p['items'][1]['product_id'] = $p['items'][0]['product_id'];

                    return $p;
                },
                ['items.0.product_id', 'items.1.product_id'],
            ],
        ];
    }

    public function test_inactive_products_cannot_be_ordered(): void
    {
        $this->actingAsApiAdmin();
        $inactive = Product::factory()->inactive()->create();

        $payload = $this->validPayload();
        $payload['items'][1]['product_id'] = $inactive->id;

        $this->postJson('/api/purchase-orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.product_id']);
    }
}
