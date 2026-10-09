<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrders,
    ) {}

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $order = $this->purchaseOrders->create($request->toData(), $request->user());

        return PurchaseOrderResource::make($order)
            ->additional(['message' => 'Purchase order created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $purchaseOrder->load(['supplier', 'items' => fn ($query) => $query->orderBy('id'), 'items.product']);

        return PurchaseOrderResource::make($purchaseOrder)
            ->additional(['message' => 'Purchase order retrieved successfully.']);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $order = $this->purchaseOrders->updateDraft($purchaseOrder, $request->toData());

        return PurchaseOrderResource::make($order)
            ->additional(['message' => 'Purchase order updated successfully.']);
    }
}
