<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    /**
     * Display all suppliers with their product count.
     */
    public function index(): Response
    {
        return Inertia::render('stock/suppliers', [
            'suppliers' => Supplier::withCount('products')->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created supplier.
     */
    public function store(SupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fournisseur créé avec succès.']);

        return back();
    }

    /**
     * Update the specified supplier.
     */
    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fournisseur mis à jour avec succès.']);

        return back();
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fournisseur supprimé avec succès.']);

        return back();
    }
}
