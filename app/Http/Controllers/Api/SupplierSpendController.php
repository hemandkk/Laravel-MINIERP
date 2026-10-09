<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierSpendResource;
use App\Queries\SupplierSpendReport;
use App\Support\Money;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierSpendController extends Controller
{
    public function __invoke(SupplierSpendReport $report): AnonymousResourceCollection
    {
        $rows = $report->get();

        return SupplierSpendResource::collection($rows)->additional([
            'message' => 'Supplier spend report generated successfully.',
            'meta' => [
                'status' => 'RECEIVED',
                'suppliers_count' => $rows->count(),
                // Sums the already-aggregated per-supplier rows (one per supplier), not raw orders.
                'grand_total' => Money::sum($rows->map(fn ($row): string => Money::normalize($row->total_spend))),
            ],
        ]);
    }
}
