@extends('layouts.app')

@section('title', 'New Purchase Order')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a></li>
            <li class="breadcrumb-item active" aria-current="page">New</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">New Purchase Order</h1>
    <p class="text-muted">New purchase orders are always created as <x-status-badge :status="App\Enums\PurchaseOrderStatus::Draft" />.</p>

    @include('purchase-orders._form', [
        'action' => route('purchase-orders.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Purchase Order',
        'purchaseOrder' => null,
        'lines' => old('items', [['product_id' => '', 'quantity' => 1, 'unit_price' => '']]),
    ])
@endsection
