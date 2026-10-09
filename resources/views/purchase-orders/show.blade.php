@extends('layouts.app')

@section('title', $purchaseOrder->number)

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $purchaseOrder->number }}</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">
            {{ $purchaseOrder->number }}
            <x-status-badge :status="$purchaseOrder->status" class="fs-6 align-middle ms-1" />
        </h1>
        @if ($purchaseOrder->status->isEditable())
            @can('update', $purchaseOrder)
                <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn btn-outline-secondary">Edit Draft</a>
            @endcan
        @endif
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Order Details</h2></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Supplier</dt>
                        <dd class="col-sm-8">
                            <span class="fw-semibold">{{ $purchaseOrder->supplier->name }}</span><br>
                            <span class="small text-muted">{{ $purchaseOrder->supplier->email }} &middot; {{ $purchaseOrder->supplier->phone }}</span>
                        </dd>

                        <dt class="col-sm-4">Order date</dt>
                        <dd class="col-sm-8">{{ $purchaseOrder->order_date->format('M j, Y') }}</dd>

                        <dt class="col-sm-4">Created</dt>
                        <dd class="col-sm-8">
                            {{ $purchaseOrder->created_at->format('M j, Y g:i A') }}
                            @if ($purchaseOrder->creator)
                                <span class="text-muted">by {{ $purchaseOrder->creator->name }}</span>
                            @endif
                        </dd>

                        @if ($purchaseOrder->approved_at)
                            <dt class="col-sm-4">Approved</dt>
                            <dd class="col-sm-8">{{ $purchaseOrder->approved_at->format('M j, Y g:i A') }}</dd>
                        @endif
                        @if ($purchaseOrder->received_at)
                            <dt class="col-sm-4">Received</dt>
                            <dd class="col-sm-8">{{ $purchaseOrder->received_at->format('M j, Y g:i A') }}</dd>
                        @endif
                        @if ($purchaseOrder->cancelled_at)
                            <dt class="col-sm-4">Cancelled</dt>
                            <dd class="col-sm-8">{{ $purchaseOrder->cancelled_at->format('M j, Y g:i A') }}</dd>
                        @endif
                        @if ($purchaseOrder->notes)
                            <dt class="col-sm-4">Notes</dt>
                            <dd class="col-sm-8">{{ $purchaseOrder->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Lifecycle Actions</h2></div>
                <div class="card-body">
                    @can('changeStatus', $purchaseOrder)
                        <x-purchase-order-actions :purchase-order="$purchaseOrder" />
                    @else
                        <p class="text-muted small mb-0">You have read-only access to purchase orders.</p>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Line Items</h2></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                <tr>
                    <th scope="col">Product</th>
                    <th scope="col">SKU</th>
                    <th scope="col" class="text-end">Quantity</th>
                    <th scope="col" class="text-end">Unit Price</th>
                    <th scope="col" class="text-end">Subtotal</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($purchaseOrder->items as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td class="font-monospace">{{ $item->product->sku }}</td>
                        <td class="text-end">{{ number_format($item->quantity) }}</td>
                        <td class="text-end font-monospace">{{ App\Support\Money::format($item->unit_price) }}</td>
                        <td class="text-end font-monospace">{{ App\Support\Money::format($item->subtotal()) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr class="table-light">
                    <th scope="row" colspan="4" class="text-end">Grand Total</th>
                    <td class="text-end font-monospace fw-bold">{{ App\Support\Money::format($purchaseOrder->grandTotal()) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
