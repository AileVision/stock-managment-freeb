<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxRateRequest;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxRateController extends Controller
{
    /**
     * Display the tax rates and the net VAT summary for the period.
     */
    public function index(Request $request): Response
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
            'vat' => $this->vatSummary($request),
        ]);
    }

    /**
     * Compute the net VAT to remit on the period: collected on completed sales
     * minus deductible on received purchase orders, broken down by rate.
     *
     * @return array<string, mixed>
     */
    private function vatSummary(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfDay();

        $sales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        $collectedByRate = [];
        foreach ($sales as $sale) {
            $rate = (float) $sale->tax_percent;
            $collectedByRate[$rate] = ($collectedByRate[$rate] ?? 0.0) + $sale->taxAmount();
        }

        $receivedOrders = PurchaseOrder::query()
            ->with(['items.product:id,purchase_price'])
            ->whereIn('status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        $deductibleByRate = [];
        foreach ($receivedOrders as $order) {
            $rate = $order->taxPercent();
            $deductibleByRate[$rate] = ($deductibleByRate[$rate] ?? 0.0) + $order->taxAmount($order->receivedAmountHt());
        }

        $rates = array_keys($collectedByRate + $deductibleByRate);
        sort($rates);

        $byRate = collect($rates)->map(function ($rate) use ($collectedByRate, $deductibleByRate): array {
            $rate = (float) $rate;
            $collected = round((float) ($collectedByRate[$rate] ?? 0.0), 2);
            $deductible = round((float) ($deductibleByRate[$rate] ?? 0.0), 2);

            return [
                'rate' => $rate,
                'name' => $rate > 0 ? "TVA {$rate} %" : 'TVA 0 %',
                'collected' => $collected,
                'deductible' => $deductible,
                'net' => round($collected - $deductible, 2),
            ];
        })->values();

        $collected = round((float) $byRate->sum('collected'), 2);
        $deductible = round((float) $byRate->sum('deductible'), 2);

        return [
            'collected' => $collected,
            'deductible' => $deductible,
            'net' => round($collected - $deductible, 2),
            'by_rate' => $byRate,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ];
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
