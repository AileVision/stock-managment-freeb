<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\TaxRate;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SalesController extends Controller
{
    /**
     * Display the paginated sales history.
     */
    public function index(Request $request): Response
    {
        $sales = Sale::query()
            ->with(['user:id,name', 'items.product:id,name,sku,unit', 'invoice:id,sale_id,reference'])
            ->when($request->date('from'), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->input('payment_method')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Sale $sale): array => $this->presentSale($sale));

        return Inertia::render('stock/sales', [
            'sales' => $sales,
            'filters' => [
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
                'payment_method' => $request->input('payment_method'),
                'status' => $request->input('status'),
            ],
        ]);
    }

    /**
     * Show the cash register (point of sale).
     */
    public function pos(): Response
    {
        $products = Product::query()
            ->with('productWarehouses:product_id,warehouse_id,quantity')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'barcode', 'unit', 'sale_price', 'stock_quantity'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'unit' => $product->unit,
                'sale_price' => $product->sale_price,
                'stock_quantity' => $product->stock_quantity,
                'stock_by_warehouse' => $product->productWarehouses
                    ->pluck('quantity', 'warehouse_id')
                    ->map(fn ($quantity) => (int) $quantity)
                    ->all(),
            ]);

        $openSession = CashSession::query()
            ->where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->withCount(['sales as completed_sales_count' => fn ($query) => $query->where('status', 'completed')])
            ->withSum(
                ['sales as cash_total' => fn ($query) => $query->where('payment_method', 'cash')->where('status', 'completed')],
                'total',
            )
            ->first();

        return Inertia::render('stock/pos', [
            'products' => $products,
            'open_session' => $openSession !== null
                ? $this->presentSession($openSession)
                : null,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name', 'code', 'is_default']),
            'tax_rates' => TaxRate::active()->map(fn (TaxRate $taxRate): array => [
                'id' => $taxRate->id,
                'name' => $taxRate->name,
                'rate' => (float) $taxRate->rate,
                'is_default' => (bool) $taxRate->is_default,
            ])->values(),
        ]);
    }

    /**
     * Record a sale: creates the sale, decrements stock and logs a
     * stock-out movement per product.
     */
    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var array<int, array{product_id: int, quantity: int}> $validatedItems */
        $validatedItems = $validated['items'];

        $items = collect($validatedItems)->mapWithKeys(
            fn (array $item): array => [(int) $item['product_id'] => (int) $item['quantity']],
        );

        $warehouseId = (int) ($validated['warehouse_id'] ?? Warehouse::defaultId() ?? 0);

        $sale = DB::transaction(function () use ($items, $validated, $request, $warehouseId): Sale {
            $products = Product::query()
                ->whereKey($items->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Vérifie la disponibilité du stock dans l'entrepôt avant toute écriture.
            foreach ($items as $productId => $quantity) {
                $product = $products->get($productId);
                $available = $product !== null && $warehouseId > 0
                    ? $product->stockAt($warehouseId)
                    : (int) ($product->stock_quantity ?? 0);

                if ($product === null || $available < $quantity) {
                    $name = $product->name ?? 'produit inconnu';

                    throw ValidationException::withMessages([
                        'items' => "Stock insuffisant pour « {$name} » dans l’entrepôt. Quantité disponible : {$available}.",
                    ]);
                }
            }

            $subtotal = round($products->sum(
                fn (Product $product) => (float) $product->sale_price * $items[$product->id],
            ), 2);

            $discountPercent = (float) ($validated['discount_percent'] ?? 0);
            $total = round($subtotal * (1 - $discountPercent / 100), 2);

            // TVA incluse : on extrait la taxe du total au taux sélectionné.
            $taxRate = TaxRate::query()->whereKey((int) ($validated['tax_rate_id'] ?? 0))->first()
                ?? TaxRate::defaultRate();
            $taxPercent = $taxRate !== null ? (float) $taxRate->rate : 0.0;

            $reference = $this->nextReference();

            $sale = Sale::create([
                'reference' => $reference,
                'session_id' => $validated['session_id'] ?? null,
                'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                'user_id' => $request->user()->id,
                'payment_method' => $validated['payment_method'],
                'total' => $total,
                'discount_percent' => $discountPercent,
                'tax_rate_id' => $taxRate?->id,
                'tax_percent' => $taxPercent,
                'mobile_phone' => $validated['mobile_phone'] ?? null,
                'mobile_reference' => $validated['mobile_reference'] ?? null,
                'status' => Sale::STATUS_COMPLETED,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $productId => $quantity) {
                $product = $products->get($productId);

                if ($warehouseId > 0) {
                    $product->adjustStock($warehouseId, -$quantity);
                } else {
                    $product->decrement('stock_quantity', $quantity);
                }

                $sale->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $product->sale_price,
                ]);

                $product->stockMovements()->create([
                    'type' => 'out',
                    'quantity' => -$quantity,
                    'reason' => 'Vente en caisse',
                    'reference' => $reference,
                    'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                    'user_id' => $request->user()->id,
                ]);
            }

            return $sale->load('items.product:id,name,sku,unit');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vente enregistrée avec succès.']);
        Inertia::flash('sale', $this->presentSale($sale));

        return to_route('stock.sales.pos');
    }

    /**
     * Cancel a completed sale: restores the stock and logs the return.
     */
    public function destroy(Request $request, Sale $sale): RedirectResponse
    {
        if ($sale->status !== Sale::STATUS_COMPLETED) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cette vente est déjà annulée.']);

            return back();
        }

        $warehouseId = (int) ($sale->warehouse_id ?? Warehouse::defaultId() ?? 0);

        DB::transaction(function () use ($sale, $request, $warehouseId): void {
            $items = $sale->items()->with('product:id,name,sku,unit')->get();

            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);

                if ($warehouseId > 0) {
                    $product->adjustStock($warehouseId, $item->quantity);
                } else {
                    $product->increment('stock_quantity', $item->quantity);
                }

                $product->stockMovements()->create([
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'reason' => "Annulation vente {$sale->reference}",
                    'reference' => $sale->reference,
                    'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                    'user_id' => $request->user()->id,
                ]);
            }

            $sale->update(['status' => Sale::STATUS_CANCELLED]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vente annulée, le stock a été restitué.']);

        return back();
    }

    /**
     * Build a human-readable representation of a sale for Inertia.
     *
     * @return array<string, mixed>
     */
    private function presentSale(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'reference' => $sale->reference,
            'payment_method' => $sale->payment_method,
            'payment_label' => Sale::PAYMENT_METHODS[$sale->payment_method] ?? $sale->payment_method,
            'total' => (float) $sale->total,
            'discount_percent' => (float) $sale->discount_percent,
            'tax_percent' => (float) $sale->tax_percent,
            'tax_amount' => $sale->taxAmount(),
            'mobile_phone' => $sale->mobile_phone,
            'mobile_reference' => $sale->mobile_reference,
            'status' => $sale->status,
            'notes' => $sale->notes,
            'created_at' => $sale->created_at,
            'user' => $sale->user,
            'items' => $sale->items->map(fn (SaleItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'product' => $item->product,
            ]),
            'invoice' => $sale->relationLoaded('invoice') && $sale->invoice !== null
                ? ['id' => $sale->invoice->id, 'reference' => $sale->invoice->reference]
                : null,
        ];
    }

    /**
     * Stream the sale ticket as a printable PDF receipt.
     */
    public function ticketPdf(Sale $sale): SymfonyResponse
    {
        $sale->load('items.product:id,name,sku,unit');

        return Pdf::loadView('pdf.ticket', ['sale' => $sale])
            ->setPaper([0, 0, 226.77, 700], 'portrait')
            ->stream("ticket-{$sale->reference}.pdf");
    }

    /**
     * Build a human-readable representation of a cash session for Inertia.
     *
     * @return array<string, mixed>
     */
    private function presentSession(CashSession $session): array
    {
        return [
            'id' => $session->id,
            'opened_at' => $session->opened_at,
            'closed_at' => $session->closed_at,
            'opening_cash' => (float) $session->opening_cash,
            'expected_cash' => $session->expected_cash !== null ? (float) $session->expected_cash : null,
            'counted_cash' => $session->counted_cash !== null ? (float) $session->counted_cash : null,
            'difference' => $session->difference !== null ? (float) $session->difference : null,
            'cash_total' => round((float) ($session->cash_total ?? 0), 2),
            'sales_count' => $session->completed_sales_count ?? 0,
            'is_open' => $session->isOpen(),
            'notes' => $session->notes,
            'user' => $session->user,
        ];
    }

    /**
     * Generate a unique sale reference, e.g. V-20260814-0001.
     */
    private function nextReference(): string
    {
        $nextId = (int) DB::table('sales')->lockForUpdate()->max('id') + 1;

        return 'V-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }
}
