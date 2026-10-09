<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvalidStockMutationException extends DomainRuleException
{
    public static function nonPositiveQuantity(int $quantity): self
    {
        return new self("Stock can only be incremented by a positive quantity; {$quantity} given.");
    }
}
