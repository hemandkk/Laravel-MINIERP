<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


#[Fillable(['sku', 'name', 'description', 'unit_price', 'low_stock_threshold', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
            'is_low_stock' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $product->low_stock_threshold ??= (int) config('inventory.default_low_stock_threshold', 10);
        });
    }


    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }


    public function isLowStock(): bool
    {
        return $this->stock_quantity < $this->low_stock_threshold;
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }


    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->where('is_low_stock', true);
    }


    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $escaped = addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $q) => $q
            ->where('sku', 'like', $escaped)
            ->orWhere('name', 'like', $escaped));
    }
}
