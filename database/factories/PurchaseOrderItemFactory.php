<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;


class PurchaseOrderItemFactory extends Factory
{

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'unit_price' => sprintf('%d.%02d', fake()->numberBetween(1, 300), fake()->numberBetween(0, 99)),
        ];
    }
}
