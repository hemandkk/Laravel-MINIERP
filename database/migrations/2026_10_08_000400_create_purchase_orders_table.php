<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Values mirror App\Enums\PurchaseOrderStatus; listed literally so this migration stays a fixed snapshot.
            $table->enum('status', ['DRAFT', 'APPROVED', 'RECEIVED', 'CANCELLED'])->default('DRAFT');
            $table->date('order_date');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Listing / "latest orders" sort.
            $table->index('order_date');
            // Dashboard expenditure (status = RECEIVED) and status-filtered listings sorted by date.
            $table->index(['status', 'order_date']);
            // Declared before the foreign key so MySQL reuses it for the constraint instead of
            // creating a redundant single-column index; also serves the supplier-spend join.
            $table->index(['supplier_id', 'status']);

            $table->foreign('supplier_id')->references('id')->on('suppliers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
