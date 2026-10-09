<?php

namespace Tests;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function viewer(): User
    {
        return User::factory()->create();
    }

    protected function actingAsApiAdmin(): User
    {
        $user = $this->admin();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * An APPROVED order with a single line for the given product.
     */
    protected function approvedOrderFor(Product $product, int $quantity, string $unitPrice = '10.00'): PurchaseOrder
    {
        $order = PurchaseOrder::factory()->approved()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ]);

        return $order;
    }
}
