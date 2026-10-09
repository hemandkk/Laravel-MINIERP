<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;


final class PurchaseOrderQuery
{

    public function paginate(?PurchaseOrderStatus $status = null, ?int $supplierId = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->listing()
            ->when($status, fn (Builder $query) => $query->withStatus($status))
            ->when($supplierId, fn (Builder $query) => $query->where('supplier_id', $supplierId))
            ->paginate($perPage)
            ->withQueryString();
    }


    public function latest(int $limit = 5): Collection
    {
        return $this->listing()->limit($limit)->get();
    }


    private function listing(): Builder
    {
        return PurchaseOrder::query()
            ->with('supplier:id,name')
            ->withCount('items')
            ->withTotal()
            ->orderByDesc('order_date')
            ->orderByDesc('id');
    }
}
