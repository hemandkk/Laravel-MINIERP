<?php

namespace Tests\Unit;

use App\Data\PurchaseOrderData;
use App\Data\PurchaseOrderLineData;
use App\Exceptions\InvalidPurchaseOrderException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Domain-level validation that holds even when the HTTP Form Request is bypassed.
 */
class PurchaseOrderQuantityValidationTest extends TestCase
{
    #[DataProvider('invalidQuantities')]
    public function test_zero_or_negative_quantities_are_rejected(int $quantity): void
    {
        $this->expectException(InvalidPurchaseOrderException::class);
        $this->expectExceptionMessage('must be greater than zero');

        new PurchaseOrderLineData(productId: 1, quantity: $quantity, unitPrice: '10.00');
    }

    public static function invalidQuantities(): array
    {
        return ['zero' => [0], 'negative' => [-5]];
    }

    public function test_negative_unit_price_is_rejected(): void
    {
        $this->expectException(InvalidPurchaseOrderException::class);

        new PurchaseOrderLineData(productId: 1, quantity: 1, unitPrice: '-0.01');
    }

    public function test_an_order_without_lines_is_rejected(): void
    {
        $this->expectException(InvalidPurchaseOrderException::class);

        new PurchaseOrderData(supplierId: 1, items: []);
    }

    public function test_duplicate_products_are_rejected(): void
    {
        $this->expectException(InvalidPurchaseOrderException::class);
        $this->expectExceptionMessage('appears more than once');

        new PurchaseOrderData(supplierId: 1, items: [
            new PurchaseOrderLineData(1, 2, '5.00'),
            new PurchaseOrderLineData(1, 3, '5.00'),
        ]);
    }
}
