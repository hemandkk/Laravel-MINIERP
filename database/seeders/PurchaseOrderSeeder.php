<?php

namespace Database\Seeders;

use App\Data\PurchaseOrderData;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseOrderStatusService;
use Illuminate\Database\Seeder;

 //Sample orders are created 
 
class PurchaseOrderSeeder extends Seeder
{
    public function run(PurchaseOrderService $orders, PurchaseOrderStatusService $statuses): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $suppliers = Supplier::query()->pluck('id', 'name');
        $products = Product::query()->pluck('id', 'sku');

        $make = fn (string $supplier, int $daysAgo, array $lines, ?string $notes = null) => $orders->create(
            PurchaseOrderData::fromArray([
                'supplier_id' => $suppliers[$supplier],
                'order_date' => now()->subDays($daysAgo)->toDateString(),
                'notes' => $notes,
                'items' => array_map(fn (array $line) => [
                    'product_id' => $products[$line[0]],
                    'quantity' => $line[1],
                    'unit_price' => $line[2],
                ], $lines),
            ]),
            $admin,
        );

        // RECEIVED: stock is incremented by the receive action.
        $received1 = $make('Redington Ltd.', 21, [
            ['ELEC-1001', 30, '17.90'],
            ['ELEC-1003', 4, '210.00'],
        ], 'Quarterly peripherals restock.');
        $statuses->approve($received1);
        $statuses->receive($received1);

        $received2 = $make('Contoso Office Supply Co.', 14, [
            ['OFF-2003', 10, '26.00'],
            ['ELEC-1005', 25, '9.50'],
            ['OFF-2002', 2, '182.00'],
        ]);
        $statuses->approve($received2);
        $statuses->receive($received2);

        // APPROVED: awaiting delivery, stock unchanged.
        $approved = $make('Northwind Traders', 5, [
            ['ELEC-1002', 20, '61.50'],
            ['OFF-2001', 100, '4.40'],
        ], 'Urgent: keyboards and paper are below threshold.');
        $statuses->approve($approved);

        // DRAFT: still editable.
        $make('Redington Ltd.', 2, [
            ['ELEC-1004', 150, '6.80'],
            ['OFF-2004', 10, '55.00'],
        ]);

        // CANCELLED: stock unchanged.
        $cancelled = $make('Northwind Traders', 9, [
            ['ELEC-1003', 2, '225.00'],
        ], 'Supplier could not meet delivery date.');
        $statuses->cancel($cancelled);
    }
}
