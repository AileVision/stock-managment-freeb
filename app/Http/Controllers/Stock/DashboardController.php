<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the stock dashboard with key indicators.
     */
    public function index(Request $request): Response
    {
        $totalProducts = Product::count();
        $totalUnits = (int) Product::sum('stock_quantity');
        $stockValue = (float) Product::selectRaw('COALESCE(SUM(stock_quantity * purchase_price), 0) as value')
            ->value('value');
        $outOfStock = Product::where('stock_quantity', 0)->count();
        $lowStock = Product::where('min_stock', '>', 0)
            ->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->count();

        $lowStockProducts = Product::with('category:id,name')
            ->where('min_stock', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->orderByRaw('stock_quantity * 1.0 / min_stock asc')
            ->limit(8)
            ->get(['id', 'name', 'sku', 'unit', 'stock_quantity', 'min_stock', 'category_id']);

        $recentMovements = StockMovement::with(['product:id,name,sku,unit', 'user:id,name'])
            ->latest()
            ->limit(10)
            ->get(['id', 'type', 'quantity', 'reason', 'created_at', 'product_id', 'user_id']);

        // Indicateur de créances clients, visible uniquement pour les
        // utilisateurs autorisés à consulter les créances.
        $receivables = null;

        if ($request->user()->can('receivables.view')) {
            $creditSales = Sale::query()
                ->where('payment_method', 'credit')
                ->where('status', Sale::STATUS_COMPLETED)
                ->with(['payments', 'returns'])
                ->get();

            $receivables = [
                'total_outstanding' => round((float) $creditSales->sum(
                    fn (Sale $sale): float => max($sale->balance(), 0),
                ), 2),
                'overdue_total' => round((float) $creditSales->sum(
                    fn (Sale $sale): float => $sale->isOverdue() ? $sale->balance() : 0.0,
                ), 2),
                'overdue_sales' => $creditSales->filter(
                    fn (Sale $sale): bool => $sale->isOverdue(),
                )->count(),
                'customers_with_balance' => $creditSales
                    ->filter(fn (Sale $sale): bool => $sale->balance() > 0.01)
                    ->pluck('customer_id')
                    ->unique()
                    ->count(),
            ];
        }

        return Inertia::render('dashboard', [
            'stats' => [
                'total_products' => $totalProducts,
                'total_units' => $totalUnits,
                'stock_value' => round($stockValue, 2),
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
            ],
            'receivables' => $receivables,
            'low_stock_products' => $lowStockProducts,
            'recent_movements' => $recentMovements,
        ]);
    }
}
