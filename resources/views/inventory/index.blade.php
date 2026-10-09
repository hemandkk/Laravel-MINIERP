@extends('layouts.app')

@section('title', 'Inventory')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Inventory</h1>
    </div>

    <form method="GET" action="{{ route('inventory.index') }}" class="card card-body shadow-sm border-0 mb-3" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="search" class="form-label small text-muted mb-1">Search by SKU or name (prefix)</label>
                <input type="search" id="search" name="search" value="{{ $search }}" class="form-control" maxlength="100"
                       placeholder="e.g. ELEC or Wireless">
            </div>
            <div class="col-md-3">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="low_stock" name="low_stock" @checked($lowStockOnly)>
                    <label class="form-check-label" for="low_stock">Low stock only</label>
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">Products and current stock levels</caption>
                <thead class="table-light">
                <tr>
                    <th scope="col">SKU</th>
                    <th scope="col">Product Name</th>
                    <th scope="col" class="text-end">Current Stock</th>
                    <th scope="col" class="text-end">Threshold</th>
                    <th scope="col" class="text-end">Unit Price</th>
                    <th scope="col">Stock Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($products as $product)
                    @php($low = $product->isLowStock())
                    <tr @class(['table-danger' => $low && $product->is_active])>
                        <td class="font-monospace">{{ $product->sku }}</td>
                        <td>
                            {{ $product->name }}
                            @unless ($product->is_active)
                                <span class="badge text-bg-light border ms-1">Inactive</span>
                            @endunless
                        </td>
                        <td class="text-end fw-semibold">{{ number_format($product->stock_quantity) }}</td>
                        <td class="text-end text-muted">{{ number_format($product->low_stock_threshold) }}</td>
                        <td class="text-end font-monospace">{{ App\Support\Money::format($product->unit_price) }}</td>
                        <td>
                            @if ($low)
                                <span class="badge text-bg-danger">Low stock</span>
                            @else
                                <span class="badge text-bg-success">In stock</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No products match the current filters.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            <x-pagination :paginator="$products" />
        </div>
    </div>
@endsection
