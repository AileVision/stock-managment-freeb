<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    /**
     * Display the paginated sales history.
     */
    public function index(Request $request): Response
    {
        $sales = Sale::query()
            ->with(['user:id,name', 'items.product:id,name,sku,unit'])
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
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'barcode', 'unit', 'sale_price', 'stock_quantity']);

        return Inertia::render('stock/pos', [
            'products' => $products,
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

        $sale = DB::transaction(function () use ($items, $validated, $request): Sale {
            $products = Product::query()
                ->whereKey($items->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Vérifie la disponibilité du stock avant toute écriture.
            foreach ($items as $productId => $quantity) {
                $product = $products->get($productId);

                if ($product === null || $product->stock_quantity < $quantity) {
                    $name = $product->name ?? 'produit inconnu';
                    $available = $product->stock_quantity ?? 0;

                    throw ValidationException::withMessages([
                        'items' => "Stock insuffisant pour « {$name} ». Quantité disponible : {$available}.",
                    ]);
                }
            }

            $total = round($products->sum(
                fn (Product $product) => (float) $product->sale_price * $items[$product->id],
            ), 2);

            $reference = $this->nextReference();

            $sale = Sale::create([
                'reference' => $reference,
                'user_id' => $request->user()->id,
                'payment_method' => $validated['payment_method'],
                'total' => $total,
                'status' => Sale::STATUS_COMPLETED,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $productId => $quantity) {
                $product = $products->get($productId);
                $product->decrement('stock_quantity', $quantity);

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

        DB::transaction(function () use ($sale, $request): void {
            $items = $sale->items()->with('product:id,name,sku,unit')->get();

            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);
                $product->increment('stock_quantity', $item->quantity);

                $product->stockMovements()->create([
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'reason' => "Annulation vente {$sale->reference}",
                    'reference' => $sale->reference,
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
