@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0">Dashboard</h1>
        @can('create', App\Models\PurchaseOrder::class)
            <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">New Purchase Order</a>
        @endcan
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <x-metric-card title="Total Active Products" :value="number_format($metrics['active_products'])"
                           variant="primary" hint="View inventory" :href="route('inventory.index')" />
        </div>
        <div class="col-md-4">
            <x-metric-card title="Low Stock Alerts" :value="number_format($metrics['low_stock_products'])"
                           :variant="$metrics['low_stock_products'] > 0 ? 'danger' : 'success'"
                           hint="View low-stock products" :href="route('inventory.index', ['low_stock' => 1])" />
        </div>
        <div class="col-md-4">
            <x-metric-card title="Total Expenditure" :value="App\Support\Money::format($metrics['total_expenditure'])"
                           variant="success" hint="Sum of all RECEIVED purchase orders" />
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Latest Purchase Orders</h2>
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary">View all</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">The five most recent purchase orders</caption>
                <thead class="table-light">
                <tr>
                    <th scope="col">PO Number</th>
                    <th scope="col">Supplier</th>
                    <th scope="col">Order Date</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Total Value</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($latestOrders as $order)
                    <tr>
                        <td><a href="{{ route('purchase-orders.show', $order) }}" class="fw-semibold">{{ $order->number }}</a></td>
                        <td>{{ $order->supplier->name }}</td>
                        <td>{{ $order->order_date->format('M j, Y') }}</td>
                        <td><x-status-badge :status="$order->status" /></td>
                        <td class="text-end font-monospace">{{ App\Support\Money::format($order->grandTotal()) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No purchase orders yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
