<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderStatusController;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');

    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::controller(PurchaseOrderController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create')->can('create', PurchaseOrder::class);
            Route::post('/', 'store')->name('store');
            Route::get('/{purchaseOrder}', 'show')->name('show');
            Route::get('/{purchaseOrder}/edit', 'edit')->name('edit')->can('update', 'purchaseOrder');
            Route::put('/{purchaseOrder}', 'update')->name('update');
        });

        Route::controller(PurchaseOrderStatusController::class)
            ->middleware('can:changeStatus,purchaseOrder')
            ->group(function () {
                Route::patch('/{purchaseOrder}/approve', 'approve')->name('approve');
                Route::patch('/{purchaseOrder}/receive', 'receive')->name('receive');
                Route::patch('/{purchaseOrder}/cancel', 'cancel')->name('cancel');
            });
    });
});
