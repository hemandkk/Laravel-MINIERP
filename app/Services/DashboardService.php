<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Queries\PurchaseOrderQuery;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;


final class DashboardService
{
    public function __construct(
        private readonly PurchaseOrderQuery $purchaseOrders,
    ) {}


    public function metrics(): array
    {
        return [
            ...$this->productCounts(),
            'total_expenditure' => $this->totalExpenditure(),
        ];
    }


    public function productCounts(): array
    {
        $row = Product::query()
            ->active()
            ->toBase()
            ->selectRaw('COUNT(*) AS active_products')
            ->selectRaw('COALESCE(SUM(is_low_stock), 0) AS low_stock_products')
            ->first();

        return [
            'active_products' => (int) ($row->active_products ?? 0),
            'low_stock_products' => (int) ($row->low_stock_products ?? 0),
        ];
    }


    public function totalExpenditure(): string
    {
        $sum = PurchaseOrderItem::query()
            ->toBase()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.status', PurchaseOrderStatus::Received->value)
            ->sum(DB::raw(PurchaseOrder::LINE_TOTAL_SQL));

        return Money::normalize($sum);
    }


    public function latestPurchaseOrders(int $limit = 5): Collection
    {
        return $this->purchaseOrders->latest($limit);
    }
}
