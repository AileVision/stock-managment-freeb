<?php

use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Stock\CashSessionController;
use App\Http\Controllers\Stock\CategoryController;
use App\Http\Controllers\Stock\CustomerController;
use App\Http\Controllers\Stock\DashboardController;
use App\Http\Controllers\Stock\InvoiceController;
use App\Http\Controllers\Stock\ProductController;
use App\Http\Controllers\Stock\PurchaseOrderController;
use App\Http\Controllers\Stock\QuoteController;
use App\Http\Controllers\Stock\ReplenishmentController;
use App\Http\Controllers\Stock\ReportsController;
use App\Http\Controllers\Stock\SalesController;
use App\Http\Controllers\Stock\StockMovementController;
use App\Http\Controllers\Stock\SupplierController;
use App\Http\Controllers\Stock\TransferController;
use App\Http\Controllers\Stock\WarehouseController;
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

        Route::get('customers', [CustomerController::class, 'index'])
            ->middleware('can:customers.view')
            ->name('stock.customers.index');
        Route::post('customers', [CustomerController::class, 'store'])
            ->middleware('can:customers.create')
            ->name('stock.customers.store');
        Route::patch('customers/{customer}', [CustomerController::class, 'update'])
            ->middleware('can:customers.update')
            ->name('stock.customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])
            ->middleware('can:customers.delete')
            ->name('stock.customers.destroy');

        Route::get('movements', [StockMovementController::class, 'index'])
            ->middleware('can:movements.view')
            ->name('stock.movements.index');
        Route::post('movements', [StockMovementController::class, 'store'])
            ->middleware('can:movements.create')
            ->name('stock.movements.store');

        Route::get('warehouses', [WarehouseController::class, 'index'])
            ->middleware('can:warehouses.view')
            ->name('stock.warehouses.index');
        Route::post('warehouses', [WarehouseController::class, 'store'])
            ->middleware('can:warehouses.manage')
            ->name('stock.warehouses.store');
        Route::patch('warehouses/{warehouse}', [WarehouseController::class, 'update'])
            ->middleware('can:warehouses.manage')
            ->name('stock.warehouses.update');
        Route::patch('warehouses/{warehouse}/default', [WarehouseController::class, 'setDefault'])
            ->middleware('can:warehouses.manage')
            ->name('stock.warehouses.set-default');
        Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])
            ->middleware('can:warehouses.manage')
            ->name('stock.warehouses.destroy');

        Route::get('transfers', [TransferController::class, 'index'])
            ->middleware('can:transfers.view')
            ->name('stock.transfers.index');
        Route::post('transfers', [TransferController::class, 'store'])
            ->middleware('can:transfers.create')
            ->name('stock.transfers.store');

        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
            ->middleware('can:purchase_orders.view')
            ->name('stock.purchase-orders.index');
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
            ->middleware('can:purchase_orders.create')
            ->name('stock.purchase-orders.store');
        Route::post('purchase-orders/{purchaseOrder}/order', [PurchaseOrderController::class, 'order'])
            ->middleware('can:purchase_orders.update')
            ->name('stock.purchase-orders.order');
        Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])
            ->middleware('can:purchase_orders.receive')
            ->name('stock.purchase-orders.receive');
        Route::delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])
            ->middleware('can:purchase_orders.delete')
            ->name('stock.purchase-orders.destroy');

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
        Route::get('sales/{sale}/pdf', [SalesController::class, 'ticketPdf'])
            ->middleware('can:sales.view')
            ->name('stock.sales.ticket-pdf');

        Route::get('cash-sessions', [CashSessionController::class, 'index'])
            ->middleware('can:sessions.view')
            ->name('stock.sessions.index');
        Route::post('cash-sessions', [CashSessionController::class, 'store'])
            ->middleware('can:sessions.open')
            ->name('stock.sessions.store');
        Route::post('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])
            ->middleware('can:sessions.close')
            ->name('stock.sessions.close');

        Route::get('quotes', [QuoteController::class, 'index'])
            ->middleware('can:quotes.view')
            ->name('stock.quotes.index');
        Route::post('quotes', [QuoteController::class, 'store'])
            ->middleware('can:quotes.create')
            ->name('stock.quotes.store');
        Route::patch('quotes/{quote}', [QuoteController::class, 'update'])
            ->middleware('can:quotes.update')
            ->name('stock.quotes.update');
        Route::delete('quotes/{quote}', [QuoteController::class, 'destroy'])
            ->middleware('can:quotes.delete')
            ->name('stock.quotes.destroy');
        Route::patch('quotes/{quote}/status', [QuoteController::class, 'status'])
            ->middleware('can:quotes.update')
            ->name('stock.quotes.status');
        Route::post('quotes/{quote}/convert', [QuoteController::class, 'convert'])
            ->middleware('can:quotes.update')
            ->name('stock.quotes.convert');

        Route::get('invoices', [InvoiceController::class, 'index'])
            ->middleware('can:invoices.view')
            ->name('stock.invoices.index');
        Route::post('invoices', [InvoiceController::class, 'store'])
            ->middleware('can:invoices.create')
            ->name('stock.invoices.store');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
            ->middleware('can:invoices.view')
            ->name('stock.invoices.pdf');
        Route::get('invoices/export-pdf', [InvoiceController::class, 'exportPdf'])
            ->middleware('can:invoices.view')
            ->name('stock.invoices.export-pdf');

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
        Route::get('reports/sales', [ReportsController::class, 'sales'])
            ->middleware('can:reports.view')
            ->name('stock.reports.sales');
        Route::get('reports/sales/export', [ReportsController::class, 'salesExport'])
            ->middleware('can:reports.export')
            ->name('stock.reports.sales.export');
        Route::get('reports/sales/pdf', [ReportsController::class, 'salesPdf'])
            ->middleware('can:reports.view')
            ->name('stock.reports.sales.pdf');
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

        Route::get('backups', [BackupController::class, 'index'])
            ->middleware('can:backups.manage')
            ->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])
            ->middleware('can:backups.manage')
            ->name('backups.store');
        Route::get('backups/{backup}/download', [BackupController::class, 'download'])
            ->middleware('can:backups.manage')
            ->name('backups.download');
        Route::post('backups/{backup}/restore', [BackupController::class, 'restore'])
            ->middleware('can:backups.manage')
            ->name('backups.restore');
        Route::delete('backups/{backup}', [BackupController::class, 'destroy'])
            ->middleware('can:backups.manage')
            ->name('backups.destroy');
    });
});

require __DIR__.'/settings.php';
