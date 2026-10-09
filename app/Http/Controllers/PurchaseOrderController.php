<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\PurchaseOrderIndexRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Queries\PurchaseOrderQuery;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrders,
    ) {}

    public function index(PurchaseOrderIndexRequest $request, PurchaseOrderQuery $query): View
    {
        return view('purchase-orders.index', [
            'purchaseOrders' => $query->paginate($request->status(), $request->supplierId()),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => PurchaseOrderStatus::cases(),
            'filters' => [
                'status' => $request->status()?->value,
                'supplier_id' => $request->supplierId(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('purchase-orders.create', $this->formData());
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $order = $this->purchaseOrders->create($request->toData(), $request->user());

        return redirect()
            ->route('purchase-orders.show', $order)
            ->with('success', "Purchase order {$order->number} was created as DRAFT.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'supplier',
            'creator:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.product:id,sku,name',
        ]);

        return view('purchase-orders.show', ['purchaseOrder' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if (! $purchaseOrder->status->isEditable()) {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder)
                ->with('error', "Purchase order {$purchaseOrder->number} is {$purchaseOrder->status->value} and can no longer be edited.");
        }

        $purchaseOrder->load(['items' => fn ($query) => $query->orderBy('id')]);

        return view('purchase-orders.edit', [
            'purchaseOrder' => $purchaseOrder,
            ...$this->formData($purchaseOrder),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $order = $this->purchaseOrders->updateDraft($purchaseOrder, $request->toData());

        return redirect()
            ->route('purchase-orders.show', $order)
            ->with('success', "Purchase order {$order->number} was updated.");
    }


    //return array{suppliers: Collection<int, Supplier>, products: Collection<int, Product>}

    private function formData(?PurchaseOrder $purchaseOrder = null): array
    {
        $selectedProductIds = $purchaseOrder?->items->pluck('product_id')->all() ?? [];

        return [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            // Inactive products already on a draft remain visible so the form can render them.
            'products' => Product::query()
                ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $selectedProductIds))
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'unit_price', 'is_active']),
        ];
    }
}
