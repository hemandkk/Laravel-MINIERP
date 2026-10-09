@extends('layouts.app')

@section('title', "Edit {$purchaseOrder->number}")

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a></li>
            <li class="breadcrumb-item"><a href="{{ route('purchase-orders.show', $purchaseOrder) }}">{{ $purchaseOrder->number }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">Edit {{ $purchaseOrder->number }} <x-status-badge :status="$purchaseOrder->status" class="fs-6 align-middle" /></h1>

    @include('purchase-orders._form', [
        'action' => route('purchase-orders.update', $purchaseOrder),
        'method' => 'PUT',
        'submitLabel' => 'Save Changes',
        'lines' => old('items', $purchaseOrder->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
        ])->all()),
    ])
@endsection
