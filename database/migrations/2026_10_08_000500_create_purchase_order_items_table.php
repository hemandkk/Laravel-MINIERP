<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();

            // One line per product per order. Its leading column also serves the
            // purchase_order_id foreign key and "items of an order" lookups.
            $table->unique(['purchase_order_id', 'product_id']);
            $table->index('product_id');

            // Line items are owned by their order; products referenced by history cannot be deleted.
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_unit_price_non_negative CHECK (unit_price >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
