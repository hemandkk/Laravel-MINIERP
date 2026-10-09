<?php

declare(strict_types=1);

namespace App\Data;

use App\Exceptions\InvalidPurchaseOrderException;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Input for creating or editing a purchase order. Contains no totals: the
 * total is always derived from the lines, never accepted from a client.
 */
final readonly class PurchaseOrderData
{

    public function __construct(
        public int $supplierId,
        public array $items,
        public ?CarbonImmutable $orderDate = null,
        public ?string $notes = null,
    ) {
        if ($items === []) {
            throw InvalidPurchaseOrderException::noItems();
        }

        $seen = [];

        foreach ($items as $item) {
            if (isset($seen[$item->productId])) {
                throw InvalidPurchaseOrderException::duplicateProduct($item->productId);
            }

            $seen[$item->productId] = true;
        }
    }


    public static function fromArray(array $data): self
    {
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : '';

        return new self(
            supplierId: (int) $data['supplier_id'],
            items: array_values(array_map(
                PurchaseOrderLineData::fromArray(...),
                $data['items'] ?? [],
            )),
            orderDate: empty($data['order_date']) ? null : CarbonImmutable::parse($data['order_date'])->startOfDay(),
            notes: $notes === '' ? null : $notes,
        );
    }


    public function productIds(): array
    {
        return array_map(fn (PurchaseOrderLineData $item): int => $item->productId, $this->items);
    }

    public function total(): string
    {
        return Money::sum(array_map(fn (PurchaseOrderLineData $item): string => $item->subtotal(), $this->items));
    }


    public function itemAttributes(): array
    {
        return array_map(fn (PurchaseOrderLineData $item): array => $item->toAttributes(), $this->items);
    }
}
