<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;


class PurchaseOrderFactory extends Factory
{

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'created_by' => null,
            'status' => PurchaseOrderStatus::Draft,
            'order_date' => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Draft,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->afterCreating(function (PurchaseOrder $order): void {
            $order->forceFill([
                'status' => PurchaseOrderStatus::Received,
                'approved_at' => $order->approved_at ?? now()->subDay(),
                'received_at' => now(),
            ])->save();
        });
    }

    public function cancelled(): static
    {
        return $this->afterCreating(function (PurchaseOrder $order): void {
            $order->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();
        });
    }

    public function withItems(int $count = 2): static
    {
        return $this->has(PurchaseOrderItem::factory()->count($count), 'items');
    }
}
