<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReturnRequest;
use App\Models\CreditNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReturnsController extends Controller
{
    /**
     * Display the paginated list of returns and issued avoirs.
     */
    public function index(Request $request): Response
    {
        $returns = SaleReturn::query()
            ->with(['sale:id,reference', 'customer:id,name', 'user:id,name', 'items.product:id,name,sku,unit'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->string('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('sale', fn ($q) => $q->where('reference', 'like', "%{$search}%"))
                        ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SaleReturn $saleReturn): array => $this->presentReturn($saleReturn));

        $creditNotes = CreditNote::query()
            ->with(['customer:id,name', 'saleReturn:id,reference'])
            ->latest('issued_at')
            ->limit(10)
            ->get()
            ->map(fn (CreditNote $note): array => [
                'id' => $note->id,
                'reference' => $note->reference,
                'amount' => (float) $note->amount,
                'remaining' => $note->remaining(),
                'status' => $note->status,
                'issued_at' => $note->issued_at,
                'customer' => $note->customer,
                'return_reference' => $note->saleReturn?->reference,
            ])
            ->values();

        $stats = [
            'returns_count' => (int) SaleReturn::count(),
            'returns_value' => round((float) SaleReturn::sum('total'), 2),
            'refunded' => round((float) SaleReturn::sum('refund_amount'), 2),
            'avoirs_available' => round((float) CreditNote::query()
                ->where('status', CreditNote::STATUS_ISSUED)
                ->get()
                ->sum(fn (CreditNote $note): float => $note->remaining()), 2),
        ];

        return Inertia::render('stock/returns', [
            'returns' => $returns,
            'credit_notes' => $creditNotes,
            'stats' => $stats,
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    /**
     * Show the return form: pick a sale, choose the quantities and the
     * disposition (refund or avoir).
     */
    public function create(): Response
    {
        $sales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->with([
                'customer:id,name,phone',
                'items' => fn ($query) => $query
                    ->whereColumn('returned_quantity', '<', 'quantity')
                    ->with('product:id,name,sku,unit'),
                'payments',
            ])
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'reference' => $sale->reference,
                'created_at' => $sale->created_at,
                'payment_method' => $sale->payment_method,
                'payment_label' => Sale::PAYMENT_METHODS[$sale->payment_method] ?? $sale->payment_method,
                'total' => (float) $sale->total,
                'customer' => $sale->customer !== null
                    ? ['id' => $sale->customer->id, 'name' => $sale->customer->name, 'phone' => $sale->customer->phone]
                    : null,
                'balance' => $sale->balance(),
                'items' => $sale->items->map(fn (SaleItem $item): array => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'returned_quantity' => $item->returned_quantity,
                    'returnable' => $item->quantity - $item->returned_quantity,
                    'unit_price' => (float) $item->unit_price,
                    'product' => $item->product,
                ])->values(),
            ])
            ->filter(fn (array $sale): bool => $sale['items']->isNotEmpty())
            ->values();

        return Inertia::render('stock/returns/create', [
            'sales' => $sales,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name', 'code', 'is_default']),
        ]);
    }

    /**
     * Process a return: restocks the goods, logs the movements and applies
     * the disposition (cash/mobile refund or avoir).
     */
    public function store(StoreReturnRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $sale = Sale::query()
            ->with(['items', 'customer:id,name', 'payments'])
            ->findOrFail((int) $validated['sale_id']);

        $warehouseId = (int) ($validated['warehouse_id'] ?? Warehouse::defaultId() ?? 0);

        $saleReturn = DB::transaction(function () use ($validated, $sale, $request, $warehouseId): SaleReturn {
            $items = collect($validated['items'])
                ->mapWithKeys(fn (array $item): array => [(int) $item['product_id'] => (int) $item['quantity']]);

            $products = Product::query()
                ->whereKey($items->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $reference = $this->nextReturnReference();

            $returnValue = 0.0;

            foreach ($items as $productId => $quantity) {
                $saleItem = $sale->items->firstWhere('product_id', $productId);
                $product = $products->get($productId);

                $returnValue += round((float) $saleItem->unit_price * $quantity, 2);

                // Réintègre le stock et enregistre le mouvement d'entrée.
                if ($warehouseId > 0) {
                    $product->adjustStock($warehouseId, $quantity);
                } else {
                    $product->increment('stock_quantity', $quantity);
                }

                $product->stockMovements()->create([
                    'type' => 'in',
                    'quantity' => $quantity,
                    'reason' => "Retour client {$reference}",
                    'reference' => $reference,
                    'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                    'user_id' => $request->user()->id,
                ]);

                // Met à jour la quantité déjà retournée de la vente d'origine.
                $saleItem->increment('returned_quantity', $quantity);
            }

            // Le retour réduit d'abord le solde impayé d'une vente à crédit ;
            // le surplus éventuel est remboursé ou converti en avoir.
            $outstanding = $sale->balance();
            $appliedToBalance = round(min($returnValue, max($outstanding, 0.0)), 2);
            $refundAmount = round($returnValue - $appliedToBalance, 2);

            $refundMethod = match ($validated['disposition']) {
                'avoir' => SaleReturn::REFUND_AVOIR,
                default => $validated['refund_method'],
            };

            if ($refundAmount <= 0.01) {
                $refundMethod = SaleReturn::REFUND_NONE;
                $refundAmount = 0.0;
            }

            $saleReturn = SaleReturn::create([
                'reference' => $reference,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'user_id' => $request->user()->id,
                'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                'total' => $returnValue,
                'refund_method' => $refundMethod,
                'refund_amount' => $refundAmount,
                'applied_to_balance' => $appliedToBalance,
                'notes' => $validated['notes'] ?? null,
                'returned_at' => now()->toDateString(),
            ]);

            foreach ($items as $productId => $quantity) {
                $saleItem = $sale->items->firstWhere('product_id', $productId);

                $saleReturn->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $saleItem->unit_price,
                ]);
            }

            // Un avoir est émis pour la partie remboursable non restituée en espèces.
            if ($refundMethod === SaleReturn::REFUND_AVOIR && $refundAmount > 0) {
                CreditNote::create([
                    'reference' => $this->nextAvoirReference(),
                    'sale_return_id' => $saleReturn->id,
                    'customer_id' => $sale->customer_id,
                    'user_id' => $request->user()->id,
                    'amount' => $refundAmount,
                    'status' => CreditNote::STATUS_ISSUED,
                    'issued_at' => now()->toDateString(),
                    'notes' => "Avoir lié au retour {$reference}",
                ]);
            }

            return $saleReturn;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Retour {$saleReturn->reference} enregistré, le stock a été réintégré.",
        ]);

        return to_route('stock.returns.index');
    }

    /**
     * Stream the return receipt as a PDF.
     */
    public function pdf(SaleReturn $saleReturn): SymfonyResponse
    {
        $saleReturn->load(['sale', 'customer', 'user', 'items.product:id,name,sku,unit', 'warehouse:id,name']);

        return Pdf::loadView('pdf.return', ['saleReturn' => $saleReturn])
            ->setPaper([0, 0, 226.77, 700], 'portrait')
            ->stream("retour-{$saleReturn->reference}.pdf");
    }

    /**
     * Stream the credit note (avoir) as a PDF.
     */
    public function creditNotePdf(CreditNote $creditNote): SymfonyResponse
    {
        $creditNote->load(['customer', 'saleReturn']);

        return Pdf::loadView('pdf.avoir', ['creditNote' => $creditNote])
            ->stream("avoir-{$creditNote->reference}.pdf");
    }

    /**
     * Build a human-readable representation of a return for Inertia.
     *
     * @return array<string, mixed>
     */
    private function presentReturn(SaleReturn $saleReturn): array
    {
        return [
            'id' => $saleReturn->id,
            'reference' => $saleReturn->reference,
            'sale_reference' => $saleReturn->sale?->reference,
            'customer' => $saleReturn->customer !== null
                ? ['id' => $saleReturn->customer->id, 'name' => $saleReturn->customer->name]
                : null,
            'total' => (float) $saleReturn->total,
            'refund_method' => $saleReturn->refund_method,
            'refund_label' => SaleReturn::REFUND_METHODS[$saleReturn->refund_method] ?? $saleReturn->refund_method,
            'refund_amount' => (float) $saleReturn->refund_amount,
            'applied_to_balance' => (float) $saleReturn->applied_to_balance,
            'notes' => $saleReturn->notes,
            'returned_at' => $saleReturn->returned_at,
            'user' => $saleReturn->user,
            'items' => $saleReturn->items->map(fn (SaleReturnItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'product' => $item->product,
            ]),
        ];
    }

    /**
     * Generate a unique return reference, e.g. R-20260815-0001.
     */
    private function nextReturnReference(): string
    {
        $nextId = (int) DB::table('sale_returns')->lockForUpdate()->max('id') + 1;

        return 'R-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique credit note reference, e.g. A-20260815-0001.
     */
    private function nextAvoirReference(): string
    {
        $nextId = (int) DB::table('credit_notes')->lockForUpdate()->max('id') + 1;

        return 'A-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }
}
