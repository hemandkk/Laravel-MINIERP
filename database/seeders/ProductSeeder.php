<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    // Product seeder
    public function run(): void
    {
        $products = [
            ['sku' => 'ELEC-1001', 'name' => 'Wireless Optical Mouse', 'unit_price' => '18.50', 'stock_quantity' => 45, 'low_stock_threshold' => 15, 'description' => '2.4 GHz wireless mouse with USB nano receiver.'],
            ['sku' => 'ELEC-1002', 'name' => 'Mechanical Keyboard', 'unit_price' => '64.99', 'stock_quantity' => 6, 'low_stock_threshold' => 10, 'description' => 'Full-size mechanical keyboard, brown switches.'],
            ['sku' => 'ELEC-1003', 'name' => '27-inch IPS Monitor', 'unit_price' => '219.00', 'stock_quantity' => 12, 'low_stock_threshold' => 5, 'description' => '27" QHD IPS monitor with height-adjustable stand.'],
            ['sku' => 'ELEC-1004', 'name' => 'USB-C Charging Cable 2m', 'unit_price' => '7.25', 'stock_quantity' => 3, 'low_stock_threshold' => 25, 'description' => 'Braided USB-C to USB-C cable, 100 W PD.'],
            ['sku' => 'ELEC-1005', 'name' => 'HDMI 2.1 Cable 1.5m', 'unit_price' => '9.80', 'stock_quantity' => 60, 'low_stock_threshold' => 20, 'description' => 'Ultra high speed HDMI cable, 48 Gbps.'],
            ['sku' => 'OFF-2001', 'name' => 'A4 Copy Paper (500 sheets)', 'unit_price' => '4.60', 'stock_quantity' => 8, 'low_stock_threshold' => 40, 'description' => '80 gsm multipurpose white copy paper.'],
            ['sku' => 'OFF-2002', 'name' => 'Ergonomic Office Chair', 'unit_price' => '189.00', 'stock_quantity' => 4, 'low_stock_threshold' => 3, 'description' => 'Mesh-back chair with adjustable lumbar support.'],
            ['sku' => 'OFF-2003', 'name' => 'LED Desk Lamp', 'unit_price' => '27.40', 'stock_quantity' => 22, 'low_stock_threshold' => 10, 'description' => 'Dimmable LED lamp with USB charging port.'],
            ['sku' => 'OFF-2004', 'name' => 'Laser Toner Cartridge (Black)', 'unit_price' => '58.00', 'stock_quantity' => 2, 'low_stock_threshold' => 6, 'description' => 'High-yield black toner, approx. 3,000 pages.'],
            ['sku' => 'OFF-2005', 'name' => 'Aluminium Laptop Stand', 'unit_price' => '32.90', 'stock_quantity' => 15, 'low_stock_threshold' => 10, 'description' => 'Adjustable, foldable aluminium laptop riser.', 'is_active' => false],
        ];

        foreach ($products as $attributes) {
            // stock_quantity is not mass assignable; opening balances are seeded explicitly.
            (new Product)->forceFill($attributes + ['is_active' => true])->save();
        }
    }
}
