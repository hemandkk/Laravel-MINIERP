<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class InventoryQuery
{
    private const COLUMNS = [
        'id', 'sku', 'name', 'description', 'unit_price', 'stock_quantity',
        'low_stock_threshold', 'is_active', 'is_low_stock', 'created_at', 'updated_at',
    ];


    public function paginate(?string $search = null, bool $lowStockOnly = false, ?int $perPage = null): LengthAwarePaginator
    {
        return Product::query()
            ->select(self::COLUMNS)
            ->search($search)
            ->when($lowStockOnly, fn ($query) => $query->active()->lowStock())
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage ?? (int) config('inventory.per_page'))
            ->withQueryString();
    }


    public function lowStock(?string $search = null, ?int $perPage = null): LengthAwarePaginator
    {
        return Product::query()
            ->select(self::COLUMNS)
            ->active()
            ->lowStock()
            ->search($search)
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->paginate($perPage ?? (int) config('inventory.per_page'))
            ->withQueryString();
    }
}
