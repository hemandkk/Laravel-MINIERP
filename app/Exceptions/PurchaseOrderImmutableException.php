<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PurchaseOrderStatus;

final class PurchaseOrderImmutableException extends DomainRuleException
{
    public static function forStatus(PurchaseOrderStatus $status): self
    {
        return new self("Purchase order is {$status->value} and can no longer be modified.");
    }

    public static function notEditable(PurchaseOrderStatus $status): self
    {
        if ($status->isTerminal()) {
            return self::forStatus($status);
        }

        return new self("Only DRAFT purchase orders can be edited; this purchase order is {$status->value}.");
    }
}
