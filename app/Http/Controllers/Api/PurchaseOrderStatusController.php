<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePurchaseOrderStatusRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderStatusService;

class PurchaseOrderStatusController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderStatusService $statusService,
    ) {}

    public function update(UpdatePurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $order = $this->statusService
            ->transition($purchaseOrder, $request->targetStatus())
            ->load(['supplier', 'items' => fn ($query) => $query->orderBy('id'), 'items.product']);

        return PurchaseOrderResource::make($order)
            ->additional(['message' => "Purchase order status updated to {$order->status->value}."]);
    }
}
