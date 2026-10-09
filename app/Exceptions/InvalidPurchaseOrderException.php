<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvalidPurchaseOrderException extends DomainRuleException
{
    public static function noItems(): self
    {
        return new self('A purchase order must contain at least one line item.');
    }

    public static function invalidQuantity(int $productId, int $quantity): self
    {
        return new self("Quantity for product #{$productId} must be greater than zero; {$quantity} given.");
    }

    public static function negativeUnitPrice(int $productId): self
    {
        return new self("Unit price for product #{$productId} must be zero or greater.");
    }

    public static function duplicateProduct(int $productId): self
    {
        return new self("Product #{$productId} appears more than once; combine the quantities into a single line.");
    }


    public static function productsNotOrderable(array $productIds): self
    {
        return new self('The following products do not exist or are inactive: #'.implode(', #', $productIds).'.');
    }
}
