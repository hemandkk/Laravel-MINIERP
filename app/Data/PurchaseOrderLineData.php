<?php

declare(strict_types=1);

namespace App\Data;

use App\Exceptions\InvalidPurchaseOrderException;
use App\Support\Money;

/**
 * A validated purchase-order line. Invariants are enforced on construction so
 * the domain stays correct even when called outside the HTTP layer.
 */
final readonly class PurchaseOrderLineData
{
    public string $unitPrice;

    public function __construct(
        public int $productId,
        public int $quantity,
        string $unitPrice,
    ) {
        if ($quantity <= 0) {
            throw InvalidPurchaseOrderException::invalidQuantity($productId, $quantity);
        }

        $normalized = Money::normalize($unitPrice);

        if (str_starts_with($normalized, '-')) {
            throw InvalidPurchaseOrderException::negativeUnitPrice($productId);
        }

        $this->unitPrice = $normalized;
    }


    public static function fromArray(array $line): self
    {
        return new self(
            productId: (int) $line['product_id'],
            quantity: (int) $line['quantity'],
            unitPrice: (string) $line['unit_price'],
        );
    }

    public function subtotal(): string
    {
        return Money::multiply($this->unitPrice, $this->quantity);
    }

 
    public function toAttributes(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
        ];
    }
}
