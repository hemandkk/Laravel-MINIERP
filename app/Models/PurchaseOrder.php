<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderImmutableException;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;


#[Fillable(['supplier_id', 'order_date', 'notes'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;


    public const LINE_TOTAL_SQL = 'purchase_order_items.quantity * purchase_order_items.unit_price';

    protected $attributes = [
        'status' => 'DRAFT',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'immutable_date',
            'approved_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }


    protected static function booted(): void
    {
        static::updating(function (PurchaseOrder $order): void {
            self::guardAgainstTerminalOriginal($order);
        });

        static::deleting(function (PurchaseOrder $order): void {
            self::guardAgainstTerminalOriginal($order);
        });
    }

    private static function guardAgainstTerminalOriginal(PurchaseOrder $order): void
    {
        $original = PurchaseOrderStatus::tryFrom((string) $order->getRawOriginal('status'));

        if ($original?->isTerminal()) {
            throw PurchaseOrderImmutableException::forStatus($original);
        }
    }


    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }


    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }


    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

 
    #[Scope]
    protected function withTotal(Builder $query): void
    {
        $query->withSum('items as total_amount', DB::raw(self::LINE_TOTAL_SQL));
    }

    #[Scope]
    protected function withStatus(Builder $query, PurchaseOrderStatus $status): void
    {
        $query->where('status', $status->value);
    }

    protected function number(): Attribute
    {
        return Attribute::get(fn (): string => 'PO-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT));
    }

 
    public function grandTotal(): string
    {
        if (array_key_exists('total_amount', $this->attributes)) {
            return Money::normalize($this->attributes['total_amount']);
        }

        if ($this->relationLoaded('items')) {
            return Money::sum($this->items->map(fn (PurchaseOrderItem $item): string => $item->subtotal()));
        }

        return Money::normalize($this->items()->toBase()->sum(DB::raw(self::LINE_TOTAL_SQL)));
    }
}
