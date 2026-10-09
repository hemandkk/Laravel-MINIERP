<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): View
    {
        return view('dashboard', [
            'metrics' => $dashboard->metrics(),
            'latestOrders' => $dashboard->latestPurchaseOrders(5),
        ]);
    }
}
