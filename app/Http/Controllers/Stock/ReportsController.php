<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    /**
     * Show stock reports: valuation by category and movement summaries.
     */
    public function index(Request $request): Response
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $summary = [
            'total_products' => Product::count(),
            'total_units' => (int) Product::sum('stock_quantity'),
            'stock_value' => round((float) Product::selectRaw('COALESCE(SUM(stock_quantity * purchase_price), 0) as value')->value('value'), 2),
        ];

        $totalValue = $summary['stock_value'];

        /** @var array<int, array{products_count: int, stock_value: float}> $categoryStats */
        $categoryStats = DB::table('products')
            ->selectRaw('COALESCE(category_id, 0) as category_id, COUNT(*) as products_count, COALESCE(SUM(stock_quantity * purchase_price), 0) as stock_value')
            ->groupBy('category_id')
            ->get()
            ->mapWithKeys(fn (\stdClass $row): array => [
                (int) $row->category_id => [
                    'products_count' => (int) $row->products_count,
                    'stock_value' => (float) $row->stock_value,
                ],
            ])
            ->all();

        $categories = Category::orderBy('name')->get(['id', 'name', 'color'])->map(function (Category $category) use ($categoryStats, $totalValue) {
            $stats = $categoryStats[$category->id] ?? null;
            $value = (float) ($stats['stock_value'] ?? 0);

            return [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'products_count' => (int) ($stats['products_count'] ?? 0),
                'stock_value' => round($value, 2),
                'percentage' => $totalValue > 0 ? round($value / $totalValue * 100, 1) : 0,
            ];
        });

        // Regroupe les produits sans catégorie.
        $uncategorized = $categoryStats[0] ?? null;
        $uncategorizedValue = (float) ($uncategorized['stock_value'] ?? 0);
        if ($uncategorizedValue > 0) {
            $categories->push([
                'id' => 0,
                'name' => 'Sans catégorie',
                'color' => '#94a3b8',
                'products_count' => (int) ($uncategorized['products_count'] ?? 0),
                'stock_value' => round($uncategorizedValue, 2),
                'percentage' => $totalValue > 0 ? round($uncategorizedValue / $totalValue * 100, 1) : 0,
            ]);
        }

        $categories = $categories->sortByDesc('stock_value')->values();

        $movementsQuery = StockMovement::query()
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to));

        $movements = [
            'total' => (clone $movementsQuery)->count(),
            'in_count' => (clone $movementsQuery)->where('type', 'in')->count(),
            'out_count' => (clone $movementsQuery)->where('type', 'out')->count(),
            'adjustment_count' => (clone $movementsQuery)->where('type', 'adjustment')->count(),
            'in_quantity' => (int) (clone $movementsQuery)->where('type', 'in')->sum('quantity'),
            'out_quantity' => (int) abs((float) (clone $movementsQuery)->where('type', 'out')->sum('quantity')),
        ];

        return Inertia::render('stock/reports', [
            'summary' => $summary,
            'categories' => $categories,
            'movements' => $movements,
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    /**
     * Export the whole product catalog as CSV.
     */
    public function exportProducts(): StreamedResponse
    {
        $products = Product::query()
            ->with(['category:id,name', 'supplier:id,name'])
            ->orderBy('name')
            ->get();

        return $this->csvResponse('produits.csv', function ($write) use ($products) {
            $write(['Référence', 'Nom', 'Code-barres', 'Catégorie', 'Fournisseur', 'Unité', 'Prix achat', 'Prix vente', 'Stock', 'Seuil', 'Valeur stock']);

            foreach ($products as $product) {
                $write([
                    $product->sku,
                    $product->name,
                    $product->barcode,
                    $product->category?->name,
                    $product->supplier?->name,
                    $product->unit,
                    $product->purchase_price,
                    $product->sale_price,
                    $product->stock_quantity,
                    $product->min_stock,
                    number_format((float) $product->purchase_price * $product->stock_quantity, 2, ',', ' '),
                ]);
            }
        });
    }

    /**
     * Export stock movements as CSV, honoring the same filters as the page.
     */
    public function exportMovements(Request $request): StreamedResponse
    {
        $movements = StockMovement::query()
            ->with(['product:id,name,sku', 'user:id,name'])
            ->when($request->date('from'), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->latest()
            ->get();

        return $this->csvResponse('mouvements.csv', function ($write) use ($movements) {
            $write(['Date', 'Produit', 'Référence produit', 'Type', 'Quantité', 'Motif', 'Référence doc', 'Par']);

            foreach ($movements as $movement) {
                $write([
                    $movement->created_at?->format('d/m/Y H:i'),
                    $movement->product->name,
                    $movement->product->sku,
                    $movement->type,
                    $movement->quantity,
                    $movement->reason,
                    $movement->reference,
                    $movement->user?->name,
                ]);
            }
        });
    }

    /**
     * Stream a CSV file with UTF-8 BOM and a French-friendly separator.
     */
    private function csvResponse(string $filename, callable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // BOM UTF-8 pour un rendu correct dans Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            $rows(function (array $row) use ($handle): void {
                fputcsv($handle, $row, ';');
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }
}
