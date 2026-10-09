<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PurchaseOrder;


class UpdatePurchaseOrderRequest extends StorePurchaseOrderRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchaseOrder');

        return $purchaseOrder instanceof PurchaseOrder
            && (bool) $this->user()?->can('update', $purchaseOrder);
    }
}
