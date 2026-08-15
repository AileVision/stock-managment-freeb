<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    /**
     * Display the paginated purchase orders.
     */
    public function index(Request $request): Response
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'warehouse:id,name,code', 'user:id,name', 'items.product:id,name,sku,unit'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order): array => $this->presentOrder($order));

        return Inertia::render('stock/purchase-orders', [
            'orders' => $orders,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name', 'code']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'unit']),
            'filters' => [
                'status' => $request->input('status'),
                'supplier_id' => $request->input('supplier_id'),
            ],
        ]);
    }

    /**
     * Create a draft purchase order.
     */
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $order = DB::transaction(function () use ($validated, $request): PurchaseOrder {
            $order = PurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'reference' => $validated['reference'] ?? $this->nextReference(),
                'status' => PurchaseOrder::STATUS_DRAFT,
                'user_id' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ]);
            }

            return $order;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Commande {$order->reference} créée en brouillon."]);

        return to_route('stock.purchase-orders.index');
    }

    /**
     * Move a draft order to the ordered status.
     */
    public function order(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cette commande a déjà été passée.']);

            return back();
        }

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_ORDERED]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Commande {$purchaseOrder->reference} marquée comme commandée."]);

        return back();
    }

    /**
     * Record a (possibly partial) reception of the order.
     */
    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIAL], true)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'La commande doit être passée avant d’être réceptionnée.']);

            return back();
        }

        $validated = $request->validated();
        $warehouseId = (int) ($purchaseOrder->warehouse_id ?? Warehouse::defaultId() ?? 0);

        $receivedAny = false;

        DB::transaction(function () use ($validated, $purchaseOrder, $warehouseId, &$receivedAny): void {
            foreach ($validated['items'] as $line) {
                $item = $purchaseOrder->items()->find((int) $line['id']);
                $quantity = (int) $line['received_quantity'];

                if ($item === null || $quantity <= 0) {
                    continue;
                }

                $product = Product::lockForUpdate()->findOrFail($item->product_id);
                $product->adjustStock($warehouseId, $quantity);

                $product->stockMovements()->create([
                    'type' => 'in',
                    'quantity' => $quantity,
                    'reason' => "Réception commande {$purchaseOrder->reference}",
                    'reference' => $purchaseOrder->reference,
                    'warehouse_id' => $warehouseId,
                    'user_id' => auth()->id(),
                ]);

                $item->update(['received_quantity' => (int) $item->received_quantity + $quantity]);
                $receivedAny = true;
            }
        });

        if (! $receivedAny) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Aucune quantité à réceptionner.']);

            return back();
        }

        $remaining = $purchaseOrder->items()->get()->sum(fn (PurchaseOrderItem $item): int => $item->remaining());

        $purchaseOrder->update([
            'status' => $remaining === 0 ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIAL,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $remaining === 0
                ? "Commande {$purchaseOrder->reference} entièrement réceptionnée."
                : "Réception partielle enregistrée, il reste {$remaining} unité(s) à recevoir.",
        ]);

        return back();
    }

    /**
     * Remove a draft or ordered order (no stock has been moved yet).
     */
    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED], true)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cette commande a déjà été réceptionnée.']);

            return back();
        }

        $purchaseOrder->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Commande supprimée avec succès.']);

        return to_route('stock.purchase-orders.index');
    }

    /**
     * Build a human-readable representation of an order for Inertia.
     *
     * @return array<string, mixed>
     */
    private function presentOrder(PurchaseOrder $order): array
    {
        $total = $order->items->sum('quantity');
        $received = $order->items->sum('received_quantity');

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status,
            'supplier' => $order->supplier,
            'warehouse' => $order->warehouse,
            'total_quantity' => (int) $total,
            'received_quantity' => (int) $received,
            'progress' => $total > 0 ? (int) round($received / $total * 100) : 0,
            'created_at' => $order->created_at,
            'user' => $order->user,
            'items' => $order->items->map(fn (PurchaseOrderItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'received_quantity' => (int) $item->received_quantity,
                'remaining' => $item->remaining(),
                'product' => $item->product,
            ]),
        ];
    }

    /**
     * Generate a unique purchase order reference, e.g. C-20260814-0001.
     */
    private function nextReference(): string
    {
        $nextId = (int) DB::table('purchase_orders')->lockForUpdate()->max('id') + 1;

        return 'C-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }
}
