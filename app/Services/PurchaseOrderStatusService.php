<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\ReceivePurchaseOrderAction;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\PurchaseOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Entry point for every purchase-order lifecycle transition.
 */
final class PurchaseOrderStatusService
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly ReceivePurchaseOrderAction $receiveAction,
    ) {}


    public function transition(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $target): PurchaseOrder
    {
        return match ($target) {
            PurchaseOrderStatus::Approved => $this->approve($purchaseOrder),
            PurchaseOrderStatus::Received => $this->receive($purchaseOrder),
            PurchaseOrderStatus::Cancelled => $this->cancel($purchaseOrder),
            PurchaseOrderStatus::Draft => throw InvalidStatusTransitionException::between($purchaseOrder->status, $target),
        };
    }

    /**
     * DRAFT -> APPROVED. Does not touch inventory.
     */
    public function approve(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $this->transitionWithoutStockChange($purchaseOrder, PurchaseOrderStatus::Approved, 'approved_at');
    }

    /**
     * APPROVED -> RECEIVED. Increments inventory atomically.
     */
    public function receive(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $this->receiveAction->execute($purchaseOrder);
    }

    /**
     * DRAFT|APPROVED -> CANCELLED. Does not touch inventory.
     */
    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $this->transitionWithoutStockChange($purchaseOrder, PurchaseOrderStatus::Cancelled, 'cancelled_at');
    }

    /**
     * Locks the order row and validates against its freshly read status, so two
     * conflicting requests (e.g. approve vs. cancel) cannot both succeed.
     */
    private function transitionWithoutStockChange(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderStatus $target,
        string $timestampColumn,
    ): PurchaseOrder {
        return DB::transaction(function () use ($purchaseOrder, $target, $timestampColumn): PurchaseOrder {
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $order->status->ensureCanTransitionTo($target);

            $order->status = $target;
            $order->{$timestampColumn} = CarbonImmutable::now();
            $order->save();

            return $order;
        }, self::TRANSACTION_ATTEMPTS);
    }
}
