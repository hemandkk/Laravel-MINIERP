<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;


final class SupplierSpendReport
{

    public function get(): Collection
    {
        return Supplier::query()
            ->join('purchase_orders', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.status', PurchaseOrderStatus::Received->value)
            ->groupBy('suppliers.id', 'suppliers.name')
            ->select('suppliers.id', 'suppliers.name')
            ->selectRaw('COUNT(DISTINCT purchase_orders.id) AS received_orders_count')
            ->selectRaw('SUM('.PurchaseOrder::LINE_TOTAL_SQL.') AS total_spend')
            ->orderByDesc('total_spend')
            ->orderBy('suppliers.name')
            ->get();
    }
}
