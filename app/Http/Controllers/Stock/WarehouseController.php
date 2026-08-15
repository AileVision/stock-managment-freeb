<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    /**
     * Display the warehouses with their stock summaries.
     */
    public function index(Request $request): Response
    {
        $warehouses = Warehouse::query()
            ->withCount(['stockLevels as products_count' => fn ($query) => $query->where('quantity', '>', 0)])
            ->withSum('stockLevels as total_units', 'quantity')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'address' => $warehouse->address,
                'is_default' => (bool) $warehouse->is_default,
                'products_count' => $warehouse->products_count ?? 0,
                'total_units' => (int) ($warehouse->total_units ?? 0),
            ]);

        return Inertia::render('stock/warehouses', [
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Store a newly created warehouse.
     */
    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        Warehouse::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entrepôt créé avec succès.']);

        return to_route('stock.warehouses.index');
    }

    /**
     * Update the specified warehouse.
     */
    public function update(StoreWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entrepôt mis à jour avec succès.']);

        return to_route('stock.warehouses.index');
    }

    /**
     * Set the default warehouse (the previous default loses its flag).
     */
    public function setDefault(Warehouse $warehouse): RedirectResponse
    {
        Warehouse::query()->update(['is_default' => false]);
        $warehouse->update(['is_default' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entrepôt par défaut mis à jour.']);

        return to_route('stock.warehouses.index');
    }

    /**
     * Remove the specified warehouse when it holds no stock.
     */
    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->is_default) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'L’entrepôt par défaut ne peut pas être supprimé.']);

            return to_route('stock.warehouses.index');
        }

        $nonEmpty = ProductWarehouse::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->exists();

        if ($nonEmpty) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cet entrepôt contient encore du stock. Transférez-le d’abord.']);

            return to_route('stock.warehouses.index');
        }

        $warehouse->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entrepôt supprimé avec succès.']);

        return to_route('stock.warehouses.index');
    }
}
