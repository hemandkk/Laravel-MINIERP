{{-- Expects: $index (int|string "__INDEX__"), $line (array), $products --}}
@php
    $prefix = "items.{$index}";
    $fieldError = fn (string $field) => is_int($index) ? $errors->first("{$prefix}.{$field}") : null;
@endphp
<tr data-line-item>
    <td class="product-cell">
        <label class="visually-hidden" for="items-{{ $index }}-product">Product</label>
        <select id="items-{{ $index }}-product" name="items[{{ $index }}][product_id]" data-product required
                @class(['form-select', 'is-invalid' => $fieldError('product_id')])>
            <option value="">Select a product&hellip;</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" data-price="{{ $product->unit_price }}"
                        @selected((string) ($line['product_id'] ?? '') === (string) $product->id)>
                    {{ $product->sku }} &mdash; {{ $product->name }}{{ $product->is_active ? '' : ' (inactive)' }}
                </option>
            @endforeach
        </select>
        @if ($fieldError('product_id'))
            <div class="invalid-feedback">{{ $fieldError('product_id') }}</div>
        @endif
    </td>
    <td>
        <label class="visually-hidden" for="items-{{ $index }}-quantity">Quantity</label>
        <input type="number" id="items-{{ $index }}-quantity" name="items[{{ $index }}][quantity]"
               value="{{ $line['quantity'] ?? 1 }}" min="1" step="1" required inputmode="numeric" data-quantity
               @class(['form-control text-end', 'is-invalid' => $fieldError('quantity')])>
        @if ($fieldError('quantity'))
            <div class="invalid-feedback">{{ $fieldError('quantity') }}</div>
        @endif
    </td>
    <td>
        <label class="visually-hidden" for="items-{{ $index }}-unit-price">Unit price</label>
        <input type="number" id="items-{{ $index }}-unit-price" name="items[{{ $index }}][unit_price]"
               value="{{ $line['unit_price'] ?? '' }}" min="0" step="0.01" required inputmode="decimal" data-unit-price
               @class(['form-control text-end', 'is-invalid' => $fieldError('unit_price')])>
        @if ($fieldError('unit_price'))
            <div class="invalid-feedback">{{ $fieldError('unit_price') }}</div>
        @endif
    </td>
    <td class="text-end font-monospace align-middle" data-subtotal aria-live="polite">0.00</td>
    <td class="text-end">
        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-line aria-label="Remove line">Remove</button>
    </td>
</tr>
