<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InventoryFilterRequest;
use App\Queries\InventoryQuery;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(InventoryFilterRequest $request, InventoryQuery $inventory): View
    {
        return view('inventory.index', [
            'products' => $inventory->paginate($request->search(), $request->lowStockOnly(), $request->perPage()),
            'search' => $request->search(),
            'lowStockOnly' => $request->lowStockOnly(),
        ]);
    }
}
