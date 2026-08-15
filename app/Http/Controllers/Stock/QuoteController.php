<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertQuoteRequest;
use App\Http\Requests\StoreQuoteRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\TaxRate;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    /**
     * Display the paginated, filterable list of quotes.
     */
    public function index(Request $request): Response
    {
        $quotes = Quote::query()
            ->with(['user:id,name', 'items'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->string('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Quote $quote): array => $this->presentQuote($quote));

        return Inertia::render('stock/quotes', [
            'quotes' => $quotes,
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'sale_price']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'email', 'phone', 'address']),
            'tax_rates' => TaxRate::active()->map(fn (TaxRate $taxRate): array => [
                'id' => $taxRate->id,
                'name' => $taxRate->name,
                'rate' => (float) $taxRate->rate,
                'is_default' => (bool) $taxRate->is_default,
            ])->values(),
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    /**
     * Store a newly created quote with its items.
     */
    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $quote = DB::transaction(function () use ($validated, $request): Quote {
            $quote = Quote::create([
                'reference' => $this->nextReference(),
                'customer_id' => $validated['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_address' => $validated['customer_address'] ?? null,
                'status' => Quote::STATUS_DRAFT,
                'discount_percent' => $validated['discount_percent'] ?? 0,
                'tax_rate_id' => $validated['tax_rate_id'] ?? null,
                'tax_percent' => $this->taxRatePercent((int) ($validated['tax_rate_id'] ?? 0)),
                'valid_until' => $validated['valid_until'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncItems($quote, $validated['items']);

            return $quote;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Devis {$quote->reference} créé avec succès."]);

        return to_route('stock.quotes.index');
    }

    /**
     * Update the specified quote and its items.
     */
    public function update(StoreQuoteRequest $request, Quote $quote): RedirectResponse
    {
        if (! in_array($quote->status, Quote::EDITABLE_STATUSES, true)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Ce devis ne peut plus être modifié.']);

            return back();
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $quote): void {
            $quote->update([
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_address' => $validated['customer_address'] ?? null,
                'discount_percent' => $validated['discount_percent'] ?? 0,
                'tax_rate_id' => $validated['tax_rate_id'] ?? null,
                'tax_percent' => $this->taxRatePercent((int) ($validated['tax_rate_id'] ?? 0)),
                'valid_until' => $validated['valid_until'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncItems($quote, $validated['items']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Devis mis à jour avec succès.']);

        return to_route('stock.quotes.index');
    }

    /**
     * Remove the specified quote.
     */
    public function destroy(Quote $quote): RedirectResponse
    {
        if (! in_array($quote->status, Quote::EDITABLE_STATUSES, true)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Ce devis ne peut plus être supprimé.']);

            return back();
        }

        $quote->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Devis supprimé avec succès.']);

        return to_route('stock.quotes.index');
    }

    /**
     * Move the quote through its lifecycle (draft → sent → accepted/rejected).
     */
    public function status(Request $request, Quote $quote): RedirectResponse
    {
        $status = $request->input('status');

        if (! in_array($status, [Quote::STATUS_DRAFT, Quote::STATUS_SENT, Quote::STATUS_ACCEPTED, Quote::STATUS_REJECTED], true)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Statut invalide.']);

            return back();
        }

        if ($quote->status === Quote::STATUS_CONVERTED) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Ce devis est déjà converti en vente.']);

            return back();
        }

        $quote->update(['status' => $status]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Statut du devis mis à jour.']);

        return back();
    }

    /**
     * Convert an accepted quote into a sale: decrements stock, creates the
     * sale with its movements and issues the invoice.
     */
    public function convert(ConvertQuoteRequest $request, Quote $quote): RedirectResponse
    {
        if ($quote->status !== Quote::STATUS_ACCEPTED) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Seul un devis accepté peut être converti en vente.']);

            return back();
        }

        $validated = $request->validated();

        $warehouseId = (int) (Warehouse::defaultId() ?? 0);

        $sale = DB::transaction(function () use ($validated, $quote, $request, $warehouseId): Sale {
            $items = $quote->items()->get()->mapWithKeys(
                fn (QuoteItem $item): array => [(int) $item->product_id => $item],
            )->filter(fn (QuoteItem $item): bool => $item->product_id !== null);

            $products = Product::query()
                ->whereKey($items->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Vérifie la disponibilité du stock avant toute écriture.
            foreach ($items as $productId => $item) {
                $product = $products->get($productId);

                if ($product === null || $product->stock_quantity < $item->quantity) {
                    $name = $product->name ?? $item->product_name;
                    $available = $product->stock_quantity ?? 0;

                    throw ValidationException::withMessages([
                        'items' => "Stock insuffisant pour « {$name} ». Quantité disponible : {$available}.",
                    ]);
                }
            }

            $subtotal = round((float) $quote->items()->sum(DB::raw('quantity * unit_price')), 2);
            $discountPercent = (float) $quote->discount_percent;
            $total = round($subtotal * (1 - $discountPercent / 100), 2);

            $sale = Sale::create([
                'reference' => $this->nextSaleReference(),
                'user_id' => $request->user()->id,
                'payment_method' => $validated['payment_method'],
                'total' => $total,
                'discount_percent' => $discountPercent,
                'tax_rate_id' => $quote->tax_rate_id,
                'tax_percent' => $quote->tax_percent,
                'mobile_phone' => $validated['mobile_phone'] ?? null,
                'mobile_reference' => $validated['mobile_reference'] ?? null,
                'status' => Sale::STATUS_COMPLETED,
                'notes' => "Vente issue du devis {$quote->reference}",
            ]);

            foreach ($items as $productId => $item) {
                $product = $products->get($productId);

                if ($warehouseId > 0) {
                    $product->adjustStock($warehouseId, -$item->quantity);
                } else {
                    $product->decrement('stock_quantity', $item->quantity);
                }

                $sale->items()->create([
                    'product_id' => $productId,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]);

                $product->stockMovements()->create([
                    'type' => 'out',
                    'quantity' => -$item->quantity,
                    'reason' => "Vente issue du devis {$quote->reference}",
                    'reference' => $sale->reference,
                    'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                    'user_id' => $request->user()->id,
                ]);
            }

            // La facture est émise automatiquement avec les informations du client.
            Invoice::create([
                'reference' => $this->nextInvoiceReference(),
                'sale_id' => $sale->id,
                'user_id' => $request->user()->id,
                'customer_name' => $quote->customer_name,
                'customer_email' => $quote->customer_email,
                'customer_address' => $quote->customer_address,
                'issued_at' => now()->toDateString(),
                'notes' => $quote->notes,
            ]);

            $quote->update([
                'status' => Quote::STATUS_CONVERTED,
                'sale_id' => $sale->id,
            ]);

            return $sale;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Devis converti en vente {$sale->reference}, stock mis à jour et facture émise.",
        ]);

        return to_route('stock.invoices.index');
    }

    /**
     * Replace the items of a quote, snapshotting names and prices.
     *
     * @param  array<int, array{product_id: int, quantity: int, unit_price: float|string}>  $items
     */
    private function syncItems(Quote $quote, array $items): void
    {
        $names = Product::query()
            ->whereKey(array_column($items, 'product_id'))
            ->pluck('name', 'id');

        $quote->items()->delete();

        foreach ($items as $item) {
            $quote->items()->create([
                'product_id' => $item['product_id'],
                'product_name' => $names[(int) $item['product_id']] ?? 'Produit supprimé',
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }
    }

    /**
     * Build a human-readable representation of a quote for Inertia.
     *
     * @return array<string, mixed>
     */
    private function presentQuote(Quote $quote): array
    {
        $subtotal = round((float) $quote->items->sum(fn (QuoteItem $item): float => $item->quantity * (float) $item->unit_price), 2);
        $discountPercent = (float) $quote->discount_percent;
        $discountAmount = round($subtotal * $discountPercent / 100, 2);
        $total = round($subtotal - $discountAmount, 2);

        $taxRate = $quote->taxRate;
        $taxPercent = $taxRate !== null ? (float) $taxRate->rate : 0.0;
        $taxAmount = $taxPercent > 0 ? round($total - $total / (1 + $taxPercent / 100), 2) : 0.0;

        return [
            'id' => $quote->id,
            'reference' => $quote->reference,
            'customer_name' => $quote->customer_name,
            'customer_email' => $quote->customer_email,
            'customer_phone' => $quote->customer_phone,
            'customer_address' => $quote->customer_address,
            'status' => $quote->status,
            'valid_until' => $quote->valid_until,
            'notes' => $quote->notes,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'tax_rate_id' => $quote->tax_rate_id,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'created_at' => $quote->created_at,
            'user' => $quote->user,
            'items' => $quote->items->map(fn (QuoteItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ]),
            'sale_reference' => $quote->sale?->reference,
        ];
    }

    /**
     * Resolve the percentage of a tax rate by id.
     */
    private function taxRatePercent(int $taxRateId): float
    {
        if ($taxRateId <= 0) {
            return 0.0;
        }

        $rate = TaxRate::query()->find($taxRateId);

        return $rate !== null ? (float) $rate->rate : 0.0;
    }

    /**
     * Generate a unique quote reference, e.g. D-20260814-0001.
     */
    private function nextReference(): string
    {
        $nextId = (int) DB::table('quotes')->lockForUpdate()->max('id') + 1;

        return 'D-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique sale reference, e.g. V-20260814-0001.
     */
    private function nextSaleReference(): string
    {
        $nextId = (int) DB::table('sales')->lockForUpdate()->max('id') + 1;

        return 'V-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique invoice reference, e.g. F-20260814-0001.
     */
    private function nextInvoiceReference(): string
    {
        $nextId = (int) DB::table('invoices')->lockForUpdate()->max('id') + 1;

        return 'F-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }
}
