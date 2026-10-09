<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PurchaseOrderStatus;

final class InvalidStatusTransitionException extends DomainRuleException
{
    public static function between(PurchaseOrderStatus $from, PurchaseOrderStatus $to): self
    {
        return new self("Purchase order cannot transition from {$from->value} to {$to->value}.");
    }
}
