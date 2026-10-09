<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidPurchaseOrderException;
use App\Exceptions\PurchaseOrderImmutableException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PurchaseOrderService
{

    public function create(PurchaseOrderData $data, ?User $creator = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $creator): PurchaseOrder {
            $this->ensureProductsAreOrderable($data);

            $order = new PurchaseOrder([
                'supplier_id' => $data->supplierId,
                'order_date' => $data->orderDate ?? CarbonImmutable::today(),
                'notes' => $data->notes,
            ]);
            $order->status = PurchaseOrderStatus::Draft;
            $order->created_by = $creator?->getKey();
            $order->save();

            $order->items()->createMany($data->itemAttributes());

            return $order->load(['supplier', 'items.product']);
        });
    }


    public function updateDraft(PurchaseOrder $purchaseOrder, PurchaseOrderData $data): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data): PurchaseOrder {
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $order->status->isEditable()) {
                throw PurchaseOrderImmutableException::notEditable($order->status);
            }

            $this->ensureProductsAreOrderable($data);

            $order->fill([
                'supplier_id' => $data->supplierId,
                'order_date' => $data->orderDate ?? $order->order_date,
                'notes' => $data->notes,
            ])->save();

            $order->items()->delete();
            $order->items()->createMany($data->itemAttributes());

            return $order->load(['supplier', 'items.product']);
        });
    }


    private function ensureProductsAreOrderable(PurchaseOrderData $data): void
    {
        $requested = $data->productIds();

        $orderable = Product::query()
            ->active()
            ->whereKey($requested)
            ->pluck('id')
            ->all();

        $missing = array_values(array_diff($requested, $orderable));

        if ($missing !== []) {
            throw InvalidPurchaseOrderException::productsNotOrderable($missing);
        }
    }
}
