<?php

use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Stock\CategoryController;
use App\Http\Controllers\Stock\DashboardController;
use App\Http\Controllers\Stock\ProductController;
use App\Http\Controllers\Stock\ReplenishmentController;
use App\Http\Controllers\Stock\ReportsController;
use App\Http\Controllers\Stock\SalesController;
use App\Http\Controllers\Stock\StockMovementController;
use App\Http\Controllers\Stock\SupplierController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('can:dashboard.view')
        ->name('dashboard');

    Route::prefix('stock')->group(function () {
        Route::get('products', [ProductController::class, 'index'])
            ->middleware('can:products.view')
            ->name('stock.products.index');
        Route::post('products', [ProductController::class, 'store'])
            ->middleware('can:products.create')
            ->name('stock.products.store');
        Route::patch('products/{product}', [ProductController::class, 'update'])
            ->middleware('can:products.update')
            ->name('stock.products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->middleware('can:products.delete')
            ->name('stock.products.destroy');

        Route::get('categories', [CategoryController::class, 'index'])
            ->middleware('can:categories.view')
            ->name('stock.categories.index');
        Route::post('categories', [CategoryController::class, 'store'])
            ->middleware('can:categories.create')
            ->name('stock.categories.store');
        Route::patch('categories/{category}', [CategoryController::class, 'update'])
            ->middleware('can:categories.update')
            ->name('stock.categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware('can:categories.delete')
            ->name('stock.categories.destroy');

        Route::get('suppliers', [SupplierController::class, 'index'])
            ->middleware('can:suppliers.view')
            ->name('stock.suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])
            ->middleware('can:suppliers.create')
            ->name('stock.suppliers.store');
        Route::patch('suppliers/{supplier}', [SupplierController::class, 'update'])
            ->middleware('can:suppliers.update')
            ->name('stock.suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])
            ->middleware('can:suppliers.delete')
            ->name('stock.suppliers.destroy');

        Route::get('movements', [StockMovementController::class, 'index'])
            ->middleware('can:movements.view')
            ->name('stock.movements.index');
        Route::post('movements', [StockMovementController::class, 'store'])
            ->middleware('can:movements.create')
            ->name('stock.movements.store');

        Route::get('sales', [SalesController::class, 'index'])
            ->middleware('can:sales.view')
            ->name('stock.sales.index');
        Route::get('sales/pos', [SalesController::class, 'pos'])
            ->middleware('can:sales.view')
            ->name('stock.sales.pos');
        Route::post('sales', [SalesController::class, 'store'])
            ->middleware('can:sales.create')
            ->name('stock.sales.store');
        Route::delete('sales/{sale}', [SalesController::class, 'destroy'])
            ->middleware('can:sales.delete')
            ->name('stock.sales.destroy');

        Route::get('replenishment', [ReplenishmentController::class, 'index'])
            ->middleware('can:replenishment.view')
            ->name('stock.replenishment.index');
        Route::post('replenishment', [ReplenishmentController::class, 'store'])
            ->middleware('can:replenishment.create')
            ->name('stock.replenishment.store');

        Route::get('reports', [ReportsController::class, 'index'])
            ->middleware('can:reports.view')
            ->name('stock.reports.index');
        Route::get('reports/products/export', [ReportsController::class, 'exportProducts'])
            ->middleware('can:reports.export')
            ->name('stock.reports.products.export');
        Route::get('reports/movements/export', [ReportsController::class, 'exportMovements'])
            ->middleware('can:reports.export')
            ->name('stock.reports.movements.export');
    });

    Route::prefix('admin')->group(function () {
        Route::get('users', [UsersController::class, 'index'])
            ->middleware('can:users.manage')
            ->name('users.index');
        Route::post('users', [UsersController::class, 'store'])
            ->middleware('can:users.manage')
            ->name('users.store');
        Route::patch('users/{user}', [UsersController::class, 'update'])
            ->middleware('can:users.manage')
            ->name('users.update');
        Route::delete('users/{user}', [UsersController::class, 'destroy'])
            ->middleware('can:users.manage')
            ->name('users.destroy');

        Route::get('roles', [RolesController::class, 'index'])
            ->middleware('can:roles.manage')
            ->name('roles.index');
        Route::patch('roles/{role}', [RolesController::class, 'update'])
            ->middleware('can:roles.manage')
            ->name('roles.update');
    });
});

require __DIR__.'/settings.php';
