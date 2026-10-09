<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name', 150)->index();
            $table->text('description')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            // UNSIGNED: the database itself rejects any write that would make stock negative.
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(10);
            $table->boolean('is_active')->default(true);
            // "Low stock" compares two columns, which a plain B-tree index on stock_quantity
            // cannot serve. A stored generated column makes the predicate indexable and can
            // never drift from the underlying values.
            $table->boolean('is_low_stock')->storedAs('stock_quantity < low_stock_threshold');
            $table->timestamps();

            $table->index(['is_active', 'is_low_stock']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_unit_price_non_negative CHECK (unit_price >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
