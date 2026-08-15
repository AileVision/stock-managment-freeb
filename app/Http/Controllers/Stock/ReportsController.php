<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
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
            ->mapWithKeys(fn (stdClass $row): array => [
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
     * Show the advanced sales report: revenue, margins and best sellers.
     */
    public function sales(Request $request): Response
    {
        return Inertia::render('stock/reports/sales', $this->salesData($request));
    }

    /**
     * Show the financial report: revenue, VAT, purchases and margins.
     */
    public function financial(Request $request): Response
    {
        return Inertia::render('stock/reports/financial', $this->financialData($request));
    }

    /**
     * Export the financial report as CSV.
     */
    public function financialExport(Request $request): StreamedResponse
    {
        $data = $this->financialData($request);

        return $this->csvResponse('rapport-financier.csv', function ($write) use ($data): void {
            $summary = $data['summary'];

            $write(['Rapport financier']);
            $write(['Période', $data['filters']['from'], '→', $data['filters']['to']]);
            $write([]);
            $write(['Chiffre d’affaires TTC', number_format((float) $summary['revenue'], 2, ',', ' ')]);
            $write(['TVA collectée', number_format((float) $summary['vat_collected'], 2, ',', ' ')]);
            $write(['Chiffre d’affaires HT', number_format((float) $summary['revenue_ht'], 2, ',', ' ')]);
            $write(['Achats (réceptions)', number_format((float) $summary['purchases'], 2, ',', ' ')]);
            $write(['Coût des ventes', number_format((float) $summary['cost_of_goods'], 2, ',', ' ')]);
            $write(['Marge brute', number_format((float) $summary['gross_margin'], 2, ',', ' ')]);
            $write([]);
            $write(['TVA PAR TAUX']);
            $write(['Taux', 'Base HT', 'TVA']);

            foreach ($data['vat_by_rate'] as $row) {
                $write([
                    $row['name'],
                    number_format((float) $row['base'], 2, ',', ' '),
                    number_format((float) $row['vat'], 2, ',', ' '),
                ]);
            }

            $write([]);
            $write(['ACHATS SUR LA PÉRIODE']);
            $write(['Référence', 'Fournisseur', 'Date', 'Montant HT estimé']);

            foreach ($data['purchase_orders'] as $row) {
                $write([
                    $row['reference'],
                    $row['supplier'],
                    $row['date'],
                    number_format((float) $row['amount'], 2, ',', ' '),
                ]);
            }
        });
    }

    /**
     * Compute the financial report data shared by the page and the export.
     *
     * @return array<string, mixed>
     */
    private function financialData(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfDay();

        $salesQuery = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $revenue = (float) (clone $salesQuery)->sum('total');
        $salesCount = (clone $salesQuery)->count();

        // TVA collectée : extraite du total TTC au taux enregistré sur la vente.
        $vatCollected = (float) (clone $salesQuery)->get()->sum(fn (Sale $sale): float => $sale->taxAmount());

        // Coût des ventes : quantité vendue × prix d'achat actuel du produit.
        $costOfGoods = (float) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->sum(DB::raw('sale_items.quantity * products.purchase_price'));

        // Achats : commandes réceptionnées (quantités reçues × prix d'achat).
        $purchases = (float) DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('products', 'products.id', '=', 'purchase_order_items.product_id')
            ->whereIn('purchase_orders.status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED])
            ->whereDate('purchase_orders.created_at', '>=', $from)
            ->whereDate('purchase_orders.created_at', '<=', $to)
            ->sum(DB::raw('purchase_order_items.received_quantity * products.purchase_price'));

        // TVA par taux : regroupe les ventes selon leur taux enregistré.
        $vatByRate = (clone $salesQuery)->get()
            ->groupBy('tax_percent')
            ->map(function ($grouped, $rate): array {
                $rate = (float) $rate;
                $base = round((float) $grouped->sum('total') / (1 + $rate / 100), 2);

                return [
                    'name' => $rate > 0 ? "TVA {$rate} %" : 'TVA 0 %',
                    'rate' => $rate,
                    'base' => $base,
                    'vat' => round((float) $grouped->sum(fn (Sale $sale): float => $sale->taxAmount()), 2),
                ];
            })
            ->values()
            ->sortByDesc('vat')
            ->values();

        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'items.product:id,purchase_price'])
            ->whereIn('status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->limit(25)
            ->get()
            ->map(function (PurchaseOrder $order): array {
                $amount = round((float) $order->items->sum(
                    fn ($item): float => $item->received_quantity * (float) $item->product->purchase_price,
                ), 2);

                return [
                    'reference' => $order->reference,
                    'supplier' => $order->supplier->name ?? '—',
                    'date' => $order->created_at?->toDateString(),
                    'amount' => $amount,
                ];
            });

        $revenueHt = round($revenue - $vatCollected, 2);
        $grossMargin = round($revenueHt - $costOfGoods, 2);

        return [
            'summary' => [
                'revenue' => round($revenue, 2),
                'revenue_ht' => $revenueHt,
                'vat_collected' => round($vatCollected, 2),
                'sales_count' => $salesCount,
                'purchases' => $purchases,
                'cost_of_goods' => round($costOfGoods, 2),
                'gross_margin' => $grossMargin,
                'margin_rate' => $revenueHt > 0 ? round($grossMargin / $revenueHt * 100, 1) : 0,
            ],
            'vat_by_rate' => $vatByRate,
            'purchase_orders' => $purchaseOrders,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ];
    }

    /**
     * Export the sales report as CSV.
     */
    public function salesExport(Request $request): StreamedResponse
    {
        $data = $this->salesData($request);

        return $this->csvResponse('rapport-ventes.csv', function ($write) use ($data): void {
            $summary = $data['summary'];

            $write(['Rapport de ventes']);
            $write(['Période', $data['filters']['from'], '→', $data['filters']['to']]);
            $write([]);
            $write(['Chiffre d’affaires', number_format((float) $summary['revenue'], 2, ',', ' ')]);
            $write(['Nombre de ventes', (int) $summary['sales_count']]);
            $write(['Panier moyen', number_format((float) $summary['average_basket'], 2, ',', ' ')]);
            $write(['Marge brute', number_format((float) $summary['margin'], 2, ',', ' ')]);
            $write(['Taux de marge', number_format((float) $summary['margin_rate'], 1, ',', ' ').' %']);
            $write([]);
            $write(['CA PAR JOUR']);
            $write(['Date', 'Ventes', 'CA']);

            foreach ($data['daily'] as $row) {
                $write([$row['day'], (int) $row['sales_count'], number_format((float) $row['revenue'], 2, ',', ' ')]);
            }

            $write([]);
            $write(['MEILLEURES VENTES']);
            $write(['Produit', 'Référence', 'Qté', 'CA', 'Coût', 'Marge', 'Taux %']);

            foreach ($data['top_products'] as $row) {
                $write([
                    $row['name'],
                    $row['sku'],
                    (int) $row['quantity'],
                    number_format((float) $row['revenue'], 2, ',', ' '),
                    number_format((float) $row['cost'], 2, ',', ' '),
                    number_format((float) $row['margin'], 2, ',', ' '),
                    number_format((float) $row['margin_rate'], 1, ',', ' '),
                ]);
            }
        });
    }

    /**
     * Download the sales report as PDF.
     */
    public function salesPdf(Request $request): SymfonyResponse
    {
        $data = $this->salesData($request);

        return Pdf::loadView('pdf.sales-report', $data)
            ->setPaper('a4', 'landscape')
            ->stream('rapport-ventes.pdf');
    }

    /**
     * Compute the sales report data shared by the page and the exports.
     *
     * @return array<string, mixed>
     */
    private function salesData(Request $request): array
    {
        $from = $request->date('from') ?? now()->subDays(29)->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        $salesQuery = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $revenue = (float) (clone $salesQuery)->sum('total');
        $salesCount = (clone $salesQuery)->count();

        // Coût des ventes : quantité vendue × prix d'achat actuel du produit.
        $cost = (float) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->sum(DB::raw('sale_items.quantity * products.purchase_price'));

        $margin = round($revenue - $cost, 2);

        /** @var Collection<int, stdClass> $daily */
        $daily = DB::table('sales')
            ->selectRaw('date(created_at) as day, COUNT(*) as sales_count, SUM(total) as revenue')
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn (stdClass $row): array => [
                'day' => $row->day,
                'sales_count' => (int) $row->sales_count,
                'revenue' => round((float) $row->revenue, 2),
            ]);

        $byPayment = DB::table('sales')
            ->selectRaw('payment_method, COUNT(*) as sales_count, SUM(total) as revenue')
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->groupBy('payment_method')
            ->get()
            ->map(fn (stdClass $row): array => [
                'payment_method' => $row->payment_method,
                'payment_label' => Sale::PAYMENT_METHODS[$row->payment_method] ?? $row->payment_method,
                'sales_count' => (int) $row->sales_count,
                'revenue' => round((float) $row->revenue, 2),
            ]);

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->selectRaw(
                'sale_items.product_id, products.name, products.sku, '
                .'SUM(sale_items.quantity) as quantity, '
                .'SUM(sale_items.quantity * sale_items.unit_price) as revenue, '
                .'SUM(sale_items.quantity * products.purchase_price) as cost'
            )
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->groupBy('sale_items.product_id', 'products.name', 'products.sku')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(function (stdClass $row): array {
                $revenue = (float) $row->revenue;
                $cost = (float) $row->cost;

                return [
                    'product_id' => (int) $row->product_id,
                    'name' => $row->name,
                    'sku' => $row->sku,
                    'quantity' => (int) $row->quantity,
                    'revenue' => round($revenue, 2),
                    'cost' => round($cost, 2),
                    'margin' => round($revenue - $cost, 2),
                    'margin_rate' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : 0,
                ];
            });

        return [
            'summary' => [
                'revenue' => round($revenue, 2),
                'sales_count' => $salesCount,
                'average_basket' => $salesCount > 0 ? round($revenue / $salesCount, 2) : 0,
                'margin' => $margin,
                'margin_rate' => $revenue > 0 ? round($margin / $revenue * 100, 1) : 0,
            ],
            'daily' => $daily,
            'by_payment' => $byPayment,
            'top_products' => $topProducts,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ];
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
