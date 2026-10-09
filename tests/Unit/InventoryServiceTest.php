<?php

namespace Tests\Unit;

use App\Exceptions\InvalidStockMutationException;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_increment_adds_stock_atomically(): void
    {
        $product = Product::factory()->withStock(20)->create();

        app(InventoryService::class)->increment($product, 5);

        $this->assertSame(25, $product->fresh()->stock_quantity);
    }

    #[DataProvider('nonPositiveQuantities')]
    public function test_non_positive_increments_are_rejected_and_stock_is_unchanged(int $quantity): void
    {
        $product = Product::factory()->withStock(3)->create();

        try {
            app(InventoryService::class)->increment($product, $quantity);
            $this->fail('Expected InvalidStockMutationException.');
        } catch (InvalidStockMutationException) {
            // expected
        }

        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public static function nonPositiveQuantities(): array
    {
        return ['zero' => [0], 'negative (would make stock negative)' => [-10]];
    }

    public function test_the_database_itself_rejects_negative_stock(): void
    {
        $product = Product::factory()->withStock(3)->create();

        try {
            // Bypasses the service entirely; the UNSIGNED column is the last line of defence.
            Product::query()->whereKey($product->id)->decrement('stock_quantity', 4);
            $this->fail('Expected the database to reject negative stock.');
        } catch (QueryException) {
            // expected
        }

        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_incrementing_an_unknown_product_fails(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(InventoryService::class)->increment(999_999, 1);
    }
}
