<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderStatusService;
use Illuminate\Http\RedirectResponse;


class PurchaseOrderStatusController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderStatusService $statusService,
    ) {}

    public function approve(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $order = $this->statusService->approve($purchaseOrder);

        return $this->backToOrder($order, "Purchase order {$order->number} was approved.");
    }

    public function receive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $order = $this->statusService->receive($purchaseOrder);

        return $this->backToOrder($order, "Purchase order {$order->number} was received and inventory has been updated.");
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $order = $this->statusService->cancel($purchaseOrder);

        return $this->backToOrder($order, "Purchase order {$order->number} was cancelled.");
    }

    private function backToOrder(PurchaseOrder $order, string $message): RedirectResponse
    {
        return redirect()->route('purchase-orders.show', $order)->with('success', $message);
    }
}
