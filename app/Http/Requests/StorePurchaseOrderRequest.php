<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\PurchaseOrderData;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Duplicate products: a product may appear only once per purchase order. A
 * repeated product_id is rejected (the `distinct` rule) rather than silently
 * merged, so the client always sees exactly what will be stored. A UNIQUE
 * (purchase_order_id, product_id) index backs this at the database level.
 *
 * Any client-supplied totals/subtotals are ignored: only validated() data
 * reaches the service, which derives all totals from the line items.
 */
class StorePurchaseOrderRequest extends FormRequest
{
    public const MAX_ITEMS = 100;

    public const MAX_QUANTITY = 1_000_000;

    public const MAX_UNIT_PRICE = '999999.99';

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PurchaseOrder::class);
    }


    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'order_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['required', 'array'],
            // Existence/active check is batched into a single query in after().
            'items.*.product_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_UNIT_PRICE],
        ];
    }


    public function messages(): array
    {
        return [
            'items.required' => 'The items field is required.',
            'items.min' => 'A purchase order must contain at least one line item.',
            'items.*.product_id.distinct' => 'Each product may appear only once per purchase order; combine the quantities into a single line.',
            'items.*.quantity.min' => 'The quantity must be greater than 0.',
            'items.*.unit_price.min' => 'The unit price must be 0 or greater.',
            'items.*.unit_price.decimal' => 'The unit price may have at most 2 decimal places.',
        ];
    }


    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.unit_price' => 'unit price',
        ];
    }


    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = $this->input('items');

                if (! is_array($items)) {
                    return;
                }

                $candidates = [];

                foreach ($items as $index => $item) {
                    $key = "items.{$index}.product_id";

                    if (is_array($item) && ! $validator->errors()->has($key) && filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
                        $candidates[$index] = (int) $item['product_id'];
                    }
                }

                if ($candidates === []) {
                    return;
                }

                $orderable = Product::query()
                    ->active()
                    ->whereKey(array_unique($candidates))
                    ->pluck('id')
                    ->flip();

                foreach ($candidates as $index => $productId) {
                    if (! $orderable->has($productId)) {
                        $validator->errors()->add(
                            "items.{$index}.product_id",
                            'The selected product does not exist or is inactive.',
                        );
                    }
                }
            },
        ];
    }

    public function toData(): PurchaseOrderData
    {
        return PurchaseOrderData::fromArray($this->validated());
    }
}
