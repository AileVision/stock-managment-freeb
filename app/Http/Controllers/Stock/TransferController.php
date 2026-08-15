<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    /**
     * Display the transfer history and the transfer form data.
     */
    public function index(Request $request): Response
    {
        $transfers = StockTransfer::query()
            ->with([
                'product:id,name,sku,unit',
                'fromWarehouse:id,name,code',
                'toWarehouse:id,name,code',
                'user:id,name',
            ])
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('stock/transfers', [
            'transfers' => $transfers,
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'unit']),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => [
                'product_id' => $request->input('product_id'),
            ],
        ]);
    }

    /**
     * Record a transfer: moves stock between two warehouses with traceability.
     */
    public function store(StoreTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $quantity = (int) $validated['quantity'];

        DB::transaction(function () use ($validated, $quantity, $request): void {
            $product = Product::lockForUpdate()->findOrFail((int) $validated['product_id']);
            $from = (int) $validated['from_warehouse_id'];
            $to = (int) $validated['to_warehouse_id'];

            $product->adjustStock($from, -$quantity);
            $product->adjustStock($to, $quantity);

            $product->stockMovements()->create([
                'type' => 'out',
                'quantity' => -$quantity,
                'reason' => 'Transfert entrepôt (sortie)',
                'reference' => "TR-{$from}-{$to}",
                'warehouse_id' => $from,
                'user_id' => $request->user()->id,
            ]);
            $product->stockMovements()->create([
                'type' => 'in',
                'quantity' => $quantity,
                'reason' => 'Transfert entrepôt (entrée)',
                'reference' => "TR-{$from}-{$to}",
                'warehouse_id' => $to,
                'user_id' => $request->user()->id,
            ]);

            StockTransfer::create([
                'product_id' => $product->id,
                'from_warehouse_id' => $from,
                'to_warehouse_id' => $to,
                'quantity' => $quantity,
                'notes' => $validated['notes'] ?? null,
                'user_id' => $request->user()->id,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfert enregistré avec succès.']);

        return to_route('stock.transfers.index');
    }
}
