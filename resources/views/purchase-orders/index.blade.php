@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Purchase Orders</h1>
        @can('create', App\Models\PurchaseOrder::class)
            <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">New Purchase Order</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('purchase-orders.index') }}" class="card card-body shadow-sm border-0 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="status" class="form-label small text-muted mb-1">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label for="supplier_id" class="form-label small text-muted mb-1">Supplier</label>
                <select id="supplier_id" name="supplier_id" class="form-select">
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($filters['supplier_id'] === $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">Purchase orders</caption>
                <thead class="table-light">
                <tr>
                    <th scope="col">PO Number</th>
                    <th scope="col">Supplier</th>
                    <th scope="col">Order Date</th>
                    <th scope="col" class="text-end">Lines</th>
                    <th scope="col" class="text-end">Total Value</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($purchaseOrders as $order)
                    <tr>
                        <td class="fw-semibold">{{ $order->number }}</td>
                        <td>{{ $order->supplier->name }}</td>
                        <td>{{ $order->order_date->format('M j, Y') }}</td>
                        <td class="text-end">{{ $order->items_count }}</td>
                        <td class="text-end font-monospace">{{ App\Support\Money::format($order->grandTotal()) }}</td>
                        <td><x-status-badge :status="$order->status" /></td>
                        <td class="text-end">
                            <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-sm btn-outline-primary">
                                View<span class="visually-hidden"> {{ $order->number }}</span>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No purchase orders found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            <x-pagination :paginator="$purchaseOrders" />
        </div>
    </div>
@endsection
