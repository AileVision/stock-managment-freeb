<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    /**
     * Display a paginated, filterable list of stock movements.
     */
    public function index(Request $request): Response
    {
        $movements = StockMovement::query()
            ->with(['product:id,name,sku,unit', 'user:id,name', 'warehouse:id,name,code'])
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->date('from'), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('stock/movements', [
            'movements' => $movements,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku', 'unit', 'stock_quantity']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name', 'code']),
            'filters' => [
                'product_id' => $request->input('product_id'),
                'type' => $request->input('type'),
                'warehouse_id' => $request->input('warehouse_id'),
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
            ],
        ]);
    }

    /**
     * Record a new stock movement and update the product quantity.
     */
    public function store(StoreStockMovementRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $quantity = (int) $validated['quantity'];

        $delta = match ($validated['type']) {
            'out' => -$quantity,
            'adjustment' => $quantity,
            default => $quantity,
        };

        $warehouseId = (int) ($validated['warehouse_id'] ?? Warehouse::defaultId() ?? 0);

        DB::transaction(function () use ($validated, $delta, $warehouseId): void {
            $product = Product::lockForUpdate()->findOrFail((int) $validated['product_id']);

            if ($warehouseId > 0) {
                $product->adjustStock($warehouseId, $delta);
            } else {
                $product->increment('stock_quantity', $delta);
            }

            $product->stockMovements()->create([
                'type' => $validated['type'],
                'quantity' => $delta,
                'reason' => $validated['reason'],
                'reference' => $validated['reference'] ?? null,
                'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                'user_id' => auth()->id(),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mouvement de stock enregistré avec succès.']);

        return back();
    }
}
