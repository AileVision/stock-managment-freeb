<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReplenishmentController extends Controller
{
    /**
     * Show products to reorder and the reception history.
     */
    public function index(): Response
    {
        $products = Product::query()
            ->with(['category:id,name', 'supplier:id,name'])
            ->where('min_stock', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->orderByRaw('stock_quantity * 1.0 / min_stock asc')
            ->get(['id', 'name', 'sku', 'unit', 'stock_quantity', 'min_stock', 'category_id', 'supplier_id'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'stock_quantity' => $product->stock_quantity,
                'min_stock' => $product->min_stock,
                'suggested_quantity' => max($product->min_stock * 2 - $product->stock_quantity, 1),
                'category' => $product->category,
                'supplier' => $product->supplier,
            ]);

        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'user:id,name', 'items.product:id,name,sku,unit'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'supplier' => $order->supplier,
                'reference' => $order->reference,
                'status' => $order->status,
                'total_quantity' => (int) $order->items->sum('quantity'),
                'created_at' => $order->created_at,
                'user' => $order->user,
                'items' => $order->items->map(fn (PurchaseOrderItem $item): array => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'product' => $item->product,
                ]),
            ]);

        return Inertia::render('stock/replenishment', [
            'products' => $products,
            'purchase_orders' => $purchaseOrders,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Record the reception of a purchase order: creates stock movements
     * and a purchase order record.
     */
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $order = PurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'status' => 'received',
                'user_id' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail((int) $item['product_id']);
                $quantity = (int) $item['quantity'];

                $product->increment('stock_quantity', $quantity);

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);

                $product->stockMovements()->create([
                    'type' => 'in',
                    'quantity' => $quantity,
                    'reason' => 'Réception commande fournisseur',
                    'reference' => $validated['reference'] ?? null,
                    'user_id' => auth()->id(),
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Réception enregistrée avec succès.']);

        return to_route('stock.replenishment.index');
    }
}
