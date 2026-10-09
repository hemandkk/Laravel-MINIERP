<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Northwind Traders',
                'email' => 'orders@example.com',
                'phone' => '+919946222333',
                'address' => "Mumbai India",
            ],
            [
                'name' => 'Contoso Office Supply Co.',
                'email' => 'sales@example.com',
                'phone' => '919946222444',
                'address' => "Gujarat India",
            ],
            [
                'name' => 'Redington Ltd.',
                'email' => 'procurement@example.com',
                'phone' => '919946222555',
                'address' => "Tamil Nadu, India",
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->create($supplier);
        }
    }
}
