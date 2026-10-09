{{-- Expects: $action, $method, $submitLabel, $suppliers, $products, $lines, $purchaseOrder (nullable) --}}
@php($lines = is_array($lines) ? array_filter($lines, 'is_array') : [])
<x-validation-errors />

<form method="POST" action="{{ $action }}" id="purchase-order-form" novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="supplier_id" class="form-label">Supplier <span class="text-danger" aria-hidden="true">*</span></label>
                    <select id="supplier_id" name="supplier_id" required
                            @class(['form-select', 'is-invalid' => $errors->has('supplier_id')])>
                        <option value="">Select a supplier&hellip;</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}"
                                @selected((string) old('supplier_id', $purchaseOrder?->supplier_id) === (string) $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="order_date" class="form-label">Order date</label>
                    <input type="date" id="order_date" name="order_date" max="{{ now()->toDateString() }}"
                           value="{{ old('order_date', $purchaseOrder?->order_date?->toDateString() ?? now()->toDateString()) }}"
                           @class(['form-control', 'is-invalid' => $errors->has('order_date')])>
                    @error('order_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea id="notes" name="notes" rows="2" maxlength="2000"
                              @class(['form-control', 'is-invalid' => $errors->has('notes')])>{{ old('notes', $purchaseOrder?->notes) }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Line Items</h2>
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-line>Add line</button>
        </div>
        @error('items')
            <div class="alert alert-danger rounded-0 mb-0">{{ $message }}</div>
        @enderror
        <div class="table-responsive">
            <table class="table mb-0 line-items">
                <thead class="table-light">
                <tr>
                    <th scope="col">Product</th>
                    <th scope="col" class="text-end">Quantity</th>
                    <th scope="col" class="text-end">Unit Price</th>
                    <th scope="col" class="text-end">Subtotal</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                {{-- Original keys are preserved so "items.N.field" validation errors map back to the right row. --}}
                <tbody data-line-items data-next-index="{{ $lines === [] ? 0 : max(array_keys($lines)) + 1 }}">
                @foreach ($lines as $index => $line)
                    @include('purchase-orders._line-item-row', ['index' => $index, 'line' => $line])
                @endforeach
                </tbody>
                <tfoot>
                <tr class="table-light">
                    <th scope="row" colspan="3" class="text-end">Estimated Grand Total</th>
                    <td class="text-end font-monospace fw-bold" data-grand-total aria-live="polite">0.00</td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">
            Totals shown here are estimates for convenience. The server recalculates all totals from the validated line items.
            Each product may appear only once per order.
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ $purchaseOrder ? route('purchase-orders.show', $purchaseOrder) : route('purchase-orders.index') }}"
           class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    </div>
</form>

<template id="line-item-template">
    @include('purchase-orders._line-item-row', ['index' => '__INDEX__', 'line' => []])
</template>

@push('scripts')
    <script src="{{ asset('js/purchase-order-form.js') }}"></script>
@endpush
