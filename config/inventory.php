<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Low-Stock Threshold
    |--------------------------------------------------------------------------
    |
    | Applied to newly created products that do not specify their own
    | threshold. A product is considered "low stock" when its
    | stock_quantity is strictly less than its low_stock_threshold.
    |
    */

    'default_low_stock_threshold' => (int) env('INVENTORY_LOW_STOCK_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'per_page' => (int) env('INVENTORY_PER_PAGE', 20),

];
