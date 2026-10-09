<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidStockMutationException;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class InventoryService
{

    public function increment(Product|int $product, int $quantity): void
    {
        if ($quantity <= 0) {
            throw InvalidStockMutationException::nonPositiveQuantity($quantity);
        }

        $productId = $product instanceof Product ? $product->getKey() : $product;

        $affected = Product::query()
            ->whereKey($productId)
            ->increment('stock_quantity', $quantity);

        if ($affected !== 1) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$productId]);
        }
    }
}
