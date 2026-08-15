<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxRateRequest;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaxRateController extends Controller
{
    /**
     * Display the tax rates.
     */
    public function index(): Response
    {
        return Inertia::render('stock/taxes', [
            'tax_rates' => TaxRate::query()
                ->orderBy('rate')
                ->orderBy('name')
                ->get()
                ->map(fn (TaxRate $taxRate): array => [
                    'id' => $taxRate->id,
                    'name' => $taxRate->name,
                    'rate' => (float) $taxRate->rate,
                    'is_default' => (bool) $taxRate->is_default,
                    'is_active' => (bool) $taxRate->is_active,
                ]),
        ]);
    }

    /**
     * Store a newly created tax rate.
     */
    public function store(StoreTaxRateRequest $request): RedirectResponse
    {
        TaxRate::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taux de TVA créé avec succès.']);

        return back();
    }

    /**
     * Update the specified tax rate.
     */
    public function update(StoreTaxRateRequest $request, TaxRate $taxRate): RedirectResponse
    {
        $taxRate->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taux de TVA mis à jour avec succès.']);

        return back();
    }

    /**
     * Set the default tax rate (previous default loses its flag).
     */
    public function setDefault(TaxRate $taxRate): RedirectResponse
    {
        TaxRate::query()->update(['is_default' => false]);
        $taxRate->update(['is_default' => true, 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taux de TVA par défaut mis à jour.']);

        return back();
    }

    /**
     * Remove the specified tax rate when nothing uses it.
     */
    public function destroy(TaxRate $taxRate): RedirectResponse
    {
        if ($taxRate->is_default) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Le taux par défaut ne peut pas être supprimé.']);

            return back();
        }

        $taxRate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taux de TVA supprimé avec succès.']);

        return back();
    }
}
