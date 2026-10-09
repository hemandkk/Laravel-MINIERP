<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryFilterRequest;
use App\Http\Resources\ProductResource;
use App\Queries\InventoryQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryQuery $inventory,
    ) {}

    public function index(InventoryFilterRequest $request): AnonymousResourceCollection
    {
        $products = $this->inventory->paginate($request->search(), $request->lowStockOnly(), $request->perPage());

        return ProductResource::collection($products)
            ->additional(['message' => 'Inventory retrieved successfully.']);
    }

    public function lowStock(InventoryFilterRequest $request): AnonymousResourceCollection
    {
        $products = $this->inventory->lowStock($request->search(), $request->perPage());

        return ProductResource::collection($products)
            ->additional(['message' => 'Low-stock products retrieved successfully.']);
    }
}
