<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Transitions an APPROVED purchase order to RECEIVED and books its quantities
 * into inventory - the only place in the application where stock increases.
 *
 * Concurrency strategy:
 *  1. The purchase-order row is locked with SELECT ... FOR UPDATE, so concurrent
 *     receivers of the same order are serialised.
 *  2. The status is re-read from that locked row (never trusted from the caller's
 *     possibly stale model). The first request flips it to RECEIVED; any request
 *     that was waiting on the lock then sees RECEIVED and is rejected, so stock is
 *     incremented exactly once.
 *  3. Product rows are locked in ascending id order, giving every transaction the
 *     same lock-acquisition order and preventing deadlocks between orders that
 *     share products.
 *  4. Each increment is an atomic `stock_quantity = stock_quantity + ?` UPDATE.
 *  5. Everything runs in one transaction: any failure rolls back stock and status.
 */
final class ReceivePurchaseOrderAction
{
    
    //Retries the whole transaction if MySQL reports a deadlock.
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}


    public function execute(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder): PurchaseOrder {
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $order->status->ensureCanTransitionTo(PurchaseOrderStatus::Received);

            /** @var array<int, int> $quantities product_id => total quantity */
            $quantities = $order->items()
                ->toBase()
                ->select('product_id')
                ->selectRaw('SUM(quantity) AS quantity')
                ->groupBy('product_id')
                ->orderBy('product_id')
                ->pluck('quantity', 'product_id')
                ->map(fn ($quantity): int => (int) $quantity)
                ->all();

            $lockedProductIds = Product::query()
                ->whereKey(array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            $missing = array_diff(array_keys($quantities), $lockedProductIds);

            if ($missing !== []) {
                throw (new ModelNotFoundException)->setModel(Product::class, array_values($missing));
            }

            foreach ($quantities as $productId => $quantity) {
                $this->inventory->increment((int) $productId, $quantity);
            }

            $order->status = PurchaseOrderStatus::Received;
            $order->received_at = CarbonImmutable::now();
            $order->save();

            return $order;
        }, self::TRANSACTION_ATTEMPTS);
    }
}
