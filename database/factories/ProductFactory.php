<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;


class ProductFactory extends Factory
{

    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'name' => ucwords(fake()->words(fake()->numberBetween(2, 3), true)),
            'description' => fake()->sentence(),
            'unit_price' => sprintf('%d.%02d', fake()->numberBetween(1, 500), fake()->numberBetween(0, 99)),
            'stock_quantity' => fake()->numberBetween(20, 200),
            'low_stock_threshold' => 10,
            'is_active' => true,
        ];
    }

    public function withStock(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => $quantity,
        ]);
    }

     // Stock strictly below the threshold.
  
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'low_stock_threshold' => 10,
            'stock_quantity' => fake()->numberBetween(0, 9),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
