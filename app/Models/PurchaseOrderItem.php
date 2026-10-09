<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderImmutableException;
use App\Support\Money;
use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


#[Fillable(['product_id', 'quantity', 'unit_price'])]
class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item): void {
            self::guardParentIsMutable($item->purchase_order_id);

            if ($item->exists && $item->isDirty('purchase_order_id')) {
                self::guardParentIsMutable($item->getOriginal('purchase_order_id'));
            }
        });

        static::deleting(function (PurchaseOrderItem $item): void {
            self::guardParentIsMutable($item->getOriginal('purchase_order_id'));
        });
    }

    private static function guardParentIsMutable(mixed $purchaseOrderId): void
    {
        if ($purchaseOrderId === null) {
            return;
        }

        $status = PurchaseOrderStatus::tryFrom((string) PurchaseOrder::query()
            ->whereKey($purchaseOrderId)
            ->toBase()
            ->value('status'));

        if ($status?->isTerminal()) {
            throw PurchaseOrderImmutableException::forStatus($status);
        }
    }


    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }


    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }


    public function subtotal(): string
    {
        return Money::multiply((string) $this->unit_price, (int) $this->quantity);
    }
}
