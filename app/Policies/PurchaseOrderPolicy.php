<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Who may act on purchase orders. Whether an action is valid for the order's
 * current state is a business rule enforced by the services (HTTP 422), not here.
 */
final class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin();
    }

    public function changeStatus(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin();
    }
}
